<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Observer;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Post;
use Panth\Blog\Model\IndexNow\PostStoreUrls;
use Panth\Blog\Model\Url\UrlHistoryManager;
use Panth\Blog\Observer\PostDeleteAfter;
use Panth\Blog\Observer\PostDeleteBefore;
use Panth\Blog\Observer\PostSaveAfter;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PostSaveAfterTest extends TestCase
{
    use BlogTestHelpers;

    private function observer(object $post): Observer
    {
        return new Observer(['event' => new Event(['post' => $post])]);
    }

    private function config(bool $enabled = true, bool $publish = true, bool $update = false, bool $delete = true): Config
    {
        $config = $this->createStub(Config::class);
        $config->method('isIndexNowEnabled')->willReturn($enabled);
        $config->method('isNotifyOnPublish')->willReturn($publish);
        $config->method('isNotifyOnUpdate')->willReturn($update);
        $config->method('isNotifyOnDelete')->willReturn($delete);
        return $config;
    }

    private function resource(AdapterInterface $connection): ResourceConnection
    {
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        return $resource;
    }

    private function urlBuilder(string $url = 'https://s.test/blog/p', array $assigned = [0]): PostStoreUrls
    {
        $urls = $this->createStub(PostStoreUrls::class);
        $urls->method('getAssignedStoreIds')->willReturn($assigned);
        $urls->method('normalize')->willReturnCallback(static function ($ids) {
            $ids = array_map('intval', is_array($ids) ? $ids : explode(',', (string) $ids));
            sort($ids);
            return $ids === [] || in_array(0, $ids, true) ? [0] : $ids;
        });
        $urls->method('getUrls')->willReturnCallback(static function (array $stores, string $key) use ($url) {
            if ($url === '' || $key === '') {
                return [];
            }
            if ($stores === [0]) {
                return [0 => $url];
            }
            $out = [];
            foreach ($stores as $id) {
                $out[$id] = 'https://store' . $id . '.test/blog/' . $key;
            }
            return $out;
        });
        return $urls;
    }

    private function deleteObserver(AdapterInterface $connection, Config $config, ?UrlHistoryManager $history = null, ?LoggerInterface $logger = null): PostDeleteAfter
    {
        return new PostDeleteAfter(
            $this->urlBuilder(),
            $history ?? $this->createStub(UrlHistoryManager::class),
            $this->resource($connection),
            $config,
            $logger ?? $this->createStub(LoggerInterface::class)
        );
    }

    private function post(array $data, array $orig = []): Post
    {
        $post = $this->makeModel(Post::class, $data);
        foreach ($orig as $key => $value) {
            $post->setOrigData($key, $value);
        }
        return $post;
    }

    private function subject(
        AdapterInterface $connection,
        ?Config $config = null,
        ?UrlHistoryManager $history = null,
        ?TypeListInterface $cache = null,
        string $url = 'https://s.test/blog/p'
    ): PostSaveAfter {
        return new PostSaveAfter(
            $this->urlBuilder($url),
            $history ?? $this->createStub(UrlHistoryManager::class),
            $this->resource($connection),
            $config ?? $this->config(),
            $cache ?? $this->createStub(TypeListInterface::class),
            $this->createStub(LoggerInterface::class)
        );
    }

    public function testNewlyPublishedPostIsQueuedForIndexNow(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('isTableExists')->willReturn(true);
        $connection->expects($this->once())->method('insert')->with('panth_blog_indexnow_queue', [
            'url' => 'https://s.test/blog/p',
            'store_id' => 0,
            'status' => 'pending',
        ]);

        $post = $this->post(['status' => 'published', 'url_key' => 'p'], ['status' => 'draft', 'url_key' => 'p']);
        $this->subject($connection)->execute($this->observer($post));
    }

    public function testDraftIsNotQueued(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->never())->method('insert');
        $this->subject($connection)->execute($this->observer($this->post(['status' => 'draft'])));
    }

    public function testRepublishOnlyQueuedWhenNotifyOnUpdate(): void
    {
        $post = $this->post(['status' => 'published', 'url_key' => 'p', 'title' => 'New'], ['status' => 'published', 'url_key' => 'p', 'title' => 'Old']);

        $silent = $this->createMock(AdapterInterface::class);
        $silent->method('isTableExists')->willReturn(true);
        $silent->expects($this->never())->method('insert');
        $this->subject($silent, $this->config(true, true, false))->execute($this->observer($post));

        $loud = $this->createMock(AdapterInterface::class);
        $loud->method('isTableExists')->willReturn(true);
        $loud->expects($this->once())->method('insert');
        $this->subject($loud, $this->config(true, true, true))->execute($this->observer($post));
    }

    public function testIndexNowDisabledOrMissingTableOrEmptyUrlSkipsQueue(): void
    {
        $post = $this->post(['status' => 'published']);

        $disabled = $this->createMock(AdapterInterface::class);
        $disabled->expects($this->never())->method('insert');
        $this->subject($disabled, $this->config(false))->execute($this->observer($post));

        $noTable = $this->createMock(AdapterInterface::class);
        $noTable->method('isTableExists')->willReturn(false);
        $noTable->expects($this->never())->method('insert');
        $this->subject($noTable)->execute($this->observer($post));

        $noUrl = $this->createMock(AdapterInterface::class);
        $noUrl->method('isTableExists')->willReturn(true);
        $noUrl->expects($this->never())->method('insert');
        $this->subject($noUrl, null, null, null, '')->execute($this->observer($post));
    }

    public function testRenamedSlugIsRecordedAndJsonLdInvalidated(): void
    {
        $history = $this->createMock(UrlHistoryManager::class);
        $history->expects($this->once())->method('recordSlugChange')->with('post', 12, 'old-slug', 'new-slug');
        $cache = $this->createMock(TypeListInterface::class);
        $cache->expects($this->once())->method('invalidate')->with('panth_blog_jsonld');

        $post = $this->post(['post_id' => 12, 'status' => 'draft', 'url_key' => 'new-slug'], ['url_key' => 'old-slug']);
        $this->subject($this->connectionStub(), null, $history, $cache)->execute($this->observer($post));
    }

    public function testUnchangedSlugIsNotRecorded(): void
    {
        $history = $this->createMock(UrlHistoryManager::class);
        $history->expects($this->never())->method('recordSlugChange');
        $post = $this->post(['post_id' => 12, 'url_key' => 'same'], ['url_key' => 'same']);
        $this->subject($this->connectionStub(), null, $history)->execute($this->observer($post));
    }

    public function testIgnoresNonPost(): void
    {
        $cache = $this->createMock(TypeListInterface::class);
        $cache->expects($this->never())->method('invalidate');
        $this->subject($this->connectionStub(), null, null, $cache)->execute($this->observer(new \stdClass()));
    }

    public function testDeleteQueuesUrlWhenEnabled(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('isTableExists')->willReturn(true);
        $connection->expects($this->once())->method('insert')
            ->with('panth_blog_indexnow_queue', ['url' => 'https://s.test/blog/p', 'store_id' => 0, 'status' => 'pending']);

        $this->deleteObserver($connection, $this->config())->execute($this->observer($this->post(['url_key' => 'p', 'status' => 'published'])));
    }

    public function testDeleteSkipsWhenNotifyOnDeleteOff(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->never())->method('insert');
        $this->deleteObserver($connection, $this->config(true, true, false, false))->execute($this->observer($this->post(['url_key' => 'p', 'status' => 'published'])));
    }

    public function testDeleteLogsFailures(): void
    {
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('isTableExists')->willThrowException(new \RuntimeException('gone'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($this->stringContains('gone'));

        $this->deleteObserver($connection, $this->config(), null, $logger)->execute($this->observer($this->post(['url_key' => 'p', 'status' => 'published'])));
    }

    public function testUnchangedSaveQueuesNothingAndKeepsCaches(): void
    {
        $data = ['status' => 'published', 'url_key' => 'p', 'title' => 'Same', 'published_at' => '2026-09-28 02:34:24'];
        $post = $this->post(
            ['status' => 'published', 'url_key' => 'p', 'title' => 'Same', 'published_at' => '2026-09-28T02:34:24.000Z'],
            $data
        );
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('isTableExists')->willReturn(true);
        $connection->expects($this->never())->method('insert');
        $cache = $this->createMock(TypeListInterface::class);
        $cache->expects($this->never())->method('invalidate');

        (new PostSaveAfter(
            $this->urlBuilder(),
            $this->createStub(UrlHistoryManager::class),
            $this->resource($connection),
            $this->config(true, true, true),
            $cache,
            $this->createStub(LoggerInterface::class)
        ))->execute($this->observer($post));
    }

    private function recordingConnection(array &$rows): AdapterInterface
    {
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('isTableExists')->willReturn(true);
        $connection->method('insert')->willReturnCallback(function ($table, array $row) use (&$rows) {
            $rows[] = [$row['store_id'], $row['url']];
            return 1;
        });
        return $connection;
    }

    private function saveObserver(AdapterInterface $connection, array $assigned, Config $config): PostSaveAfter
    {
        return new PostSaveAfter(
            $this->urlBuilder('https://s.test/blog/p', $assigned),
            $this->createStub(UrlHistoryManager::class),
            $this->resource($connection),
            $config,
            $this->createStub(TypeListInterface::class),
            $this->createStub(LoggerInterface::class)
        );
    }

    public function testNewStoreScopedPostQueuesOneUrlPerAssignedStore(): void
    {
        $rows = [];
        $post = $this->post(['post_id' => 5, 'status' => 'published', 'url_key' => 'p', 'store_ids' => ['2', '3']], []);
        $this->saveObserver($this->recordingConnection($rows), [0], $this->config())->execute($this->observer($post));
        $this->assertSame([[2, 'https://store2.test/blog/p'], [3, 'https://store3.test/blog/p']], $rows);
    }

    public function testStoreOnlyChangeQueuesAddedStoresAndRemovedStoresWhenDeletesAreNotified(): void
    {
        $data = ['post_id' => 5, 'status' => 'published', 'url_key' => 'p'];
        $rows = [];
        $post = $this->post($data + ['store_ids' => ['2', '3']], $data);
        $this->saveObserver($this->recordingConnection($rows), [1, 2], $this->config(true, true, true, true))
            ->execute($this->observer($post));
        $this->assertSame([[3, 'https://store3.test/blog/p'], [1, 'https://store1.test/blog/p']], $rows);

        $rows = [];
        $post = $this->post($data + ['store_ids' => ['2']], $data);
        $this->saveObserver($this->recordingConnection($rows), [1, 2], $this->config(true, true, true, false))
            ->execute($this->observer($post));
        $this->assertSame([], $rows);
    }

    public function testUnpublishingQueuesRemovalOnlyWhenDeletesAreNotified(): void
    {
        $rows = [];
        $post = $this->post(['post_id' => 5, 'status' => 'draft', 'url_key' => 'p'], ['post_id' => 5, 'status' => 'published', 'url_key' => 'p']);
        $this->saveObserver($this->recordingConnection($rows), [2], $this->config(true, true, true, true))
            ->execute($this->observer($post));
        $this->assertSame([[2, 'https://store2.test/blog/p']], $rows);
    }

    public function testDeleteQueuesStashedStoresAndRemovesUrlHistory(): void
    {
        $rows = [];
        $history = $this->createMock(UrlHistoryManager::class);
        $history->expects($this->once())->method('deleteForEntity')->with('post', 9);
        $post = $this->post(['post_id' => 9, 'status' => 'published', 'url_key' => 'p', PostDeleteAfter::STASH_KEY => [2]]);
        $this->deleteObserver($this->recordingConnection($rows), $this->config(true, true, true, true), $history)
            ->execute($this->observer($post));
        $this->assertSame([[2, 'https://store2.test/blog/p']], $rows);
    }

    public function testDeletingADraftOnlyRemovesHistory(): void
    {
        $rows = [];
        $history = $this->createMock(UrlHistoryManager::class);
        $history->expects($this->once())->method('deleteForEntity')->with('post', 9);
        $post = $this->post(['post_id' => 9, 'status' => 'draft', 'url_key' => 'p']);
        $this->deleteObserver($this->recordingConnection($rows), $this->config(true, true, true, true), $history)
            ->execute($this->observer($post));
        $this->assertSame([], $rows);
    }

    public function testDeleteBeforeStashesAssignedStores(): void
    {
        $post = $this->post(['post_id' => 9]);
        (new PostDeleteBefore($this->urlBuilder('x', [1, 2]), $this->createStub(LoggerInterface::class)))
            ->execute($this->observer($post));
        $this->assertSame([1, 2], $post->getData(PostDeleteAfter::STASH_KEY));
    }
}
