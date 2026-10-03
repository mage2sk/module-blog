<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Console\Command;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Console\Command\CountsRefreshCommand;
use Panth\Blog\Console\Command\IndexNowPingCommand;
use Panth\Blog\Console\Command\OgImagesGenerateCommand;
use Panth\Blog\Console\Command\PostExportCommand;
use Panth\Blog\Console\Command\TagsAutoRobotsCommand;
use Panth\Blog\Cron\RefreshPostCounts;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Post;
use Panth\Blog\Model\Url\PostUrlBuilder;
use Panth\Blog\Service\MarkdownExporter;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class SimpleCommandsTest extends TestCase
{
    use BlogTestHelpers;

    private function state(): State
    {
        $state = $this->createStub(State::class);
        $state->method('setAreaCode')->willThrowException(new \RuntimeException('area already set'));
        return $state;
    }

    private function resource(AdapterInterface $connection): ResourceConnection
    {
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        return $resource;
    }

    public function testCountsRefreshRunsCronJob(): void
    {
        $cron = $this->createMock(RefreshPostCounts::class);
        $cron->expects($this->once())->method('execute');
        $command = new CountsRefreshCommand($this->state(), $cron);
        $tester = new CommandTester($command);

        $this->assertSame('panth:blog:counts:refresh', $command->getName());
        $this->assertSame(0, $tester->execute([]));
        $this->assertStringContainsString('Post counts refreshed.', $tester->getDisplay());
    }

    public function testCountsRefreshReportsFailure(): void
    {
        $cron = $this->createStub(RefreshPostCounts::class);
        $cron->method('execute')->willThrowException(new \RuntimeException('nope'));
        $tester = new CommandTester(new CountsRefreshCommand($this->state(), $cron));
        $this->assertSame(1, $tester->execute([]));
        $this->assertStringContainsString('nope', $tester->getDisplay());
    }

    public function testOgImagesCommandIsAStub(): void
    {
        $tester = new CommandTester(new OgImagesGenerateCommand($this->state()));
        $this->assertSame(0, $tester->execute(['--post-id' => '3']));
        $this->assertStringContainsString('not implemented', $tester->getDisplay());
    }

    private function exportCommand(?PostRepositoryInterface $repository = null): CommandTester
    {
        return new CommandTester(new PostExportCommand(
            $this->state(),
            $repository ?? $this->createStub(PostRepositoryInterface::class),
            new MarkdownExporter()
        ));
    }

    public function testExportRequiresPositivePostId(): void
    {
        $tester = $this->exportCommand();
        $this->assertSame(1, $tester->execute([]));
        $this->assertStringContainsString('--post-id is required', $tester->getDisplay());
    }

    public function testExportRejectsUnknownFormat(): void
    {
        $tester = $this->exportCommand();
        $this->assertSame(1, $tester->execute(['--post-id' => '1', '--format' => 'html']));
        $this->assertStringContainsString('Only --format=markdown', $tester->getDisplay());
    }

    public function testExportWritesMarkdown(): void
    {
        $repository = $this->createMock(PostRepositoryInterface::class);
        $repository->expects($this->once())->method('getById')->with(4)
            ->willReturn($this->makeModel(Post::class, ['title' => 'Hello', 'url_key' => 'hello', 'content' => '<p>Body</p>']));
        $tester = $this->exportCommand($repository);

        $this->assertSame(0, $tester->execute(['--post-id' => '4']));
        $this->assertStringContainsString("title: \"Hello\"\nurl_key: hello", $tester->getDisplay());
        $this->assertStringContainsString('Body', $tester->getDisplay());
    }

    public function testExportReportsMissingPost(): void
    {
        $repository = $this->createStub(PostRepositoryInterface::class);
        $repository->method('getById')->willThrowException(new NoSuchEntityException(__('Missing post')));
        $tester = $this->exportCommand($repository);
        $this->assertSame(1, $tester->execute(['--post-id' => '4']));
        $this->assertStringContainsString('Missing post', $tester->getDisplay());
    }

    private function pingCommand(AdapterInterface $connection, ?PostRepositoryInterface $repository = null): CommandTester
    {
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(2);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getDefaultStoreView')->willReturn($store);
        $urls = $this->createStub(PostUrlBuilder::class);
        $urls->method('getPostUrl')->willReturn('https://s.test/blog/x');

        return new CommandTester(new IndexNowPingCommand(
            $this->state(),
            $this->resource($connection),
            $repository ?? $this->createStub(PostRepositoryInterface::class),
            $urls,
            $storeManager
        ));
    }

    public function testPingRequiresPostId(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->never())->method('insert');
        $tester = $this->pingCommand($connection);
        $this->assertSame(1, $tester->execute(['--post-id' => '0']));
    }

    public function testPingFailsWhenQueueTableMissing(): void
    {
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('isTableExists')->willReturn(false);
        $tester = $this->pingCommand($connection);
        $this->assertSame(1, $tester->execute(['--post-id' => '5']));
        $this->assertStringContainsString('queue table missing', $tester->getDisplay());
    }

    public function testPingQueuesPostUrl(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('isTableExists')->willReturn(true);
        $connection->expects($this->once())->method('insert')
            ->with('panth_blog_indexnow_queue', ['url' => 'https://s.test/blog/x', 'store_id' => 2, 'status' => 'pending']);
        $repository = $this->createStub(PostRepositoryInterface::class);
        $repository->method('getById')->willReturn($this->makeModel(Post::class));

        $tester = $this->pingCommand($connection, $repository);
        $this->assertSame(0, $tester->execute(['--post-id' => '5']));
        $this->assertStringContainsString('Queued: https://s.test/blog/x', $tester->getDisplay());
    }

    public function testTagsAutoRobotsFlipsByThreshold(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('isTableExists')->willReturn(true);
        $calls = [];
        $connection->expects($this->exactly(2))->method('update')
            ->willReturnCallback(function ($table, $bind, $where) use (&$calls) {
                $calls[] = [$table, $bind, $where];
                return count($calls) === 1 ? 4 : 9;
            });
        $config = $this->createStub(Config::class);
        $config->method('getTagThinThreshold')->willReturn(3);

        $tester = new CommandTester(new TagsAutoRobotsCommand($this->state(), $this->resource($connection), $config));
        $this->assertSame(0, $tester->execute([]));
        $this->assertSame([
            ['panth_blog_tag', ['meta_robots' => 'noindex,follow'], ['post_count < ?' => 3]],
            ['panth_blog_tag', ['meta_robots' => 'index,follow'], ['post_count >= ?' => 3]],
        ], $calls);
        $this->assertStringContainsString('Thin tags set to noindex: 4; healthy tags set to index: 9 (threshold: 3)', $tester->getDisplay());
    }

    public function testTagsAutoRobotsFailsWithoutTable(): void
    {
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('isTableExists')->willReturn(false);
        $tester = new CommandTester(new TagsAutoRobotsCommand($this->state(), $this->resource($connection), $this->createStub(Config::class)));
        $this->assertSame(1, $tester->execute([]));
    }
}
