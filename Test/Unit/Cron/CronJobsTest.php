<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Cron;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Statement\Pdo\Mysql as Statement;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Cron\ArchiveStaleDrafts;
use Panth\Blog\Cron\NotifyIndexNow;
use Panth\Blog\Cron\PublishScheduledPosts;
use Panth\Blog\Cron\RefreshPostCounts;
use Panth\Blog\Cron\WarmFeedCache;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Cache\PageCacheCleaner;
use Panth\Blog\Model\Feed\FeedRepository;
use Panth\Blog\Model\Post;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CronJobsTest extends TestCase
{
    use BlogTestHelpers;

    private function resource(AdapterInterface $connection): ResourceConnection
    {
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        return $resource;
    }

    public function testArchiveStaleDraftsUpdatesOldDrafts(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('isTableExists')->willReturn(true);
        $connection->method('fetchOne')->willReturn('2026-01-01 00:00:00');
        $connection->expects($this->once())->method('update')
            ->with('panth_blog_post', ['status' => 'archived'], ['status = ?' => 'draft', 'updated_at < ?' => '2026-01-01 00:00:00'])
            ->willReturn(3);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with($this->stringContains('archived 3 draft(s) older than 180 days'));

        (new ArchiveStaleDrafts($this->resource($connection), $logger))->execute();
    }

    public function testArchiveStaleDraftsQuietWhenNothingChanged(): void
    {
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('isTableExists')->willReturn(true);
        $connection->method('update')->willReturn(0);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');
        (new ArchiveStaleDrafts($this->resource($connection), $logger))->execute();
    }

    public function testArchiveStaleDraftsSkipsMissingTableAndLogsErrors(): void
    {
        $missing = $this->createMock(AdapterInterface::class);
        $missing->method('isTableExists')->willReturn(false);
        $missing->expects($this->never())->method('update');
        (new ArchiveStaleDrafts($this->resource($missing), $this->createStub(LoggerInterface::class)))->execute();

        $broken = $this->createStub(AdapterInterface::class);
        $broken->method('isTableExists')->willThrowException(new \RuntimeException('db gone'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($this->stringContains('db gone'));
        (new ArchiveStaleDrafts($this->resource($broken), $logger))->execute();
    }

    public function testPublishScheduledPostsPublishesDueScheduledPosts(): void
    {
        $connection = $this->connectionStub();
        $connection->method('isTableExists')->willReturn(true);
        $connection->method('fetchCol')->willReturn(['1', '2', '3']);

        $due = $this->makeModel(Post::class, ['status' => 'scheduled']);
        $alreadyPublished = $this->makeModel(Post::class, ['status' => 'published']);
        $repository = $this->createMock(PostRepositoryInterface::class);
        $repository->method('getById')->willReturnCallback(function (int $id) use ($due, $alreadyPublished) {
            return match ($id) {
                1 => $due,
                2 => $alreadyPublished,
                default => throw new \RuntimeException('missing'),
            };
        });
        $repository->expects($this->once())->method('save')->with($due);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($this->stringContains('post 3 failed: missing'));
        $logger->expects($this->once())->method('info')->with($this->stringContains('published 1 post(s)'));

        (new PublishScheduledPosts($this->resource($connection), $repository, $logger))->execute();
        $this->assertSame('published', $due->getStatus());
    }

    public function testPublishScheduledPostsSkipsMissingTable(): void
    {
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('isTableExists')->willReturn(false);
        $repository = $this->createMock(PostRepositoryInterface::class);
        $repository->expects($this->never())->method('getById');
        (new PublishScheduledPosts($this->resource($connection), $repository, $this->createStub(LoggerInterface::class)))->execute();
    }

    private function refreshConnection(int $categoryRows, int $tagRows, int $thin, int $rich): AdapterInterface
    {
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('isTableExists')->willReturn(true);
        $categoryStatement = $this->createStub(Statement::class);
        $categoryStatement->method('rowCount')->willReturn($categoryRows);
        $tagStatement = $this->createStub(Statement::class);
        $tagStatement->method('rowCount')->willReturn($tagRows);
        $connection->method('query')->willReturnOnConsecutiveCalls($categoryStatement, $tagStatement);
        $connection->method('update')->willReturnOnConsecutiveCalls($thin, $rich);
        return $connection;
    }

    public function testRefreshPostCountsCleansCacheWhenCountsChange(): void
    {
        $config = $this->createStub(Config::class);
        $config->method('getTagThinThreshold')->willReturn(3);
        $cleaner = $this->createMock(PageCacheCleaner::class);
        $cleaner->expects($this->atLeastOnce())->method('clean')->with(['panth_blog_category', 'panth_blog_tag']);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('[PanthBlog RefreshPostCounts] categories=2 tags=1 thin=0 rich=4 threshold=3');

        (new RefreshPostCounts($this->resource($this->refreshConnection(2, 1, 0, 4)), $config, $logger, $cleaner))->execute();
    }

    public function testRefreshPostCountsAppliesThresholdToRobots(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('isTableExists')->willReturn(true);
        $statement = $this->createStub(Statement::class);
        $statement->method('rowCount')->willReturn(0);
        $connection->method('query')->willReturn($statement);
        $updates = [];
        $connection->expects($this->exactly(2))->method('update')->willReturnCallback(function ($table, $bind, $where) use (&$updates) {
            $updates[] = [$table, $bind, $where];
            return 0;
        });
        $config = $this->createStub(Config::class);
        $config->method('getTagThinThreshold')->willReturn(5);
        $cleaner = $this->createMock(PageCacheCleaner::class);
        $cleaner->expects($this->never())->method('clean');

        (new RefreshPostCounts($this->resource($connection), $config, $this->createStub(LoggerInterface::class), $cleaner))->execute();

        $this->assertSame([
            ['panth_blog_tag', ['meta_robots' => 'noindex,follow'], ['post_count < ?' => 5, 'meta_robots <> ?' => 'noindex,follow']],
            ['panth_blog_tag', ['meta_robots' => 'index,follow'], ['post_count >= ?' => 5, 'meta_robots <> ?' => 'index,follow']],
        ], $updates);
    }

    public function testRefreshPostCountsSkipsWhenAnyTableMissing(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('isTableExists')->willReturnCallback(static fn ($t) => $t !== 'panth_blog_post_tag');
        $connection->expects($this->never())->method('query');
        (new RefreshPostCounts($this->resource($connection), $this->createStub(Config::class), $this->createStub(LoggerInterface::class), $this->createStub(PageCacheCleaner::class)))->execute();
    }

    private function indexNowConfig(bool $enabled, string $key = ''): Config
    {
        $config = $this->createStub(Config::class);
        $config->method('isIndexNowEnabled')->willReturn($enabled);
        $config->method('getIndexNowBatchSize')->willReturn(10);
        $config->method('getValue')->willReturn($key);
        return $config;
    }

    public function testNotifyIndexNowDoesNothingWhenDisabled(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->never())->method('isTableExists');
        (new NotifyIndexNow($this->resource($connection), $this->indexNowConfig(false), $this->createStub(LoggerInterface::class)))->execute();
    }

    public function testNotifyIndexNowStopsWhenQueueEmpty(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('isTableExists')->willReturn(true);
        $connection->method('select')->willReturn($this->selectStub());
        $connection->expects($this->once())->method('fetchAll')->willReturn([]);
        $connection->expects($this->never())->method('update');
        (new NotifyIndexNow($this->resource($connection), $this->indexNowConfig(true, 'abcdef1234'), $this->createStub(LoggerInterface::class)))->execute();
    }

    public function testNotifyIndexNowRequiresAValidKeyBeforeSending(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('isTableExists')->willReturn(true);
        $connection->method('select')->willReturn($this->selectStub());
        $connection->method('fetchAll')->willReturn([['id' => 1, 'url' => 'https://s.test/blog/a', 'store_id' => 0]]);
        $connection->expects($this->never())->method('update');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');

        (new NotifyIndexNow($this->resource($connection), $this->indexNowConfig(true, 'bad key!'), $logger))->execute();
        (new NotifyIndexNow($this->resource($connection), $this->indexNowConfig(true, 'short'), $logger))->execute();
    }

    public function testWarmFeedCacheBuildsFeedsForEnabledStores(): void
    {
        $stores = [];
        foreach ([1, 2, 3] as $id) {
            $store = $this->createStub(Store::class);
            $store->method('getId')->willReturn($id);
            $stores[] = $store;
        }
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn($stores);

        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturnCallback(static fn ($id) => $id !== 2);
        $config->method('isFeedEnabled')->willReturn(true);

        $feeds = $this->createMock(FeedRepository::class);
        $feeds->expects($this->once())->method('invalidate');
        $feeds->expects($this->exactly(2))->method('buildSiteWideRss')->willReturnCallback(static function (int $id) {
            if ($id === 3) {
                throw new \RuntimeException('render');
            }
            return '<rss/>';
        });
        $feeds->expects($this->once())->method('buildSiteWideAtom')->with(1)->willReturn('<feed/>');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($this->stringContains('store 3 failed: render'));
        $logger->expects($this->once())->method('info')->with('[PanthBlog WarmFeedCache] warmed 1 store(s)');

        (new WarmFeedCache($storeManager, $logger, $feeds, $config))->execute();
    }
}
