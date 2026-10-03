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
use Panth\Blog\Model\Url\PostUrlBuilder;
use Panth\Blog\Model\Url\UrlHistoryManager;
use Panth\Blog\Observer\PostDeleteAfter;
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

    private function urlBuilder(string $url = 'https://s.test/blog/p'): PostUrlBuilder
    {
        $builder = $this->createStub(PostUrlBuilder::class);
        $builder->method('getPostUrl')->willReturn($url);
        return $builder;
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
        $post = $this->post(['status' => 'published', 'url_key' => 'p'], ['status' => 'published', 'url_key' => 'p']);

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

        $observer = new PostDeleteAfter($this->urlBuilder(), $this->resource($connection), $this->config(), $this->createStub(LoggerInterface::class));
        $observer->execute($this->observer($this->post(['url_key' => 'p'])));
    }

    public function testDeleteSkipsWhenNotifyOnDeleteOff(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->never())->method('insert');
        $observer = new PostDeleteAfter($this->urlBuilder(), $this->resource($connection), $this->config(true, true, false, false), $this->createStub(LoggerInterface::class));
        $observer->execute($this->observer($this->post(['url_key' => 'p'])));
    }

    public function testDeleteLogsFailures(): void
    {
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('isTableExists')->willThrowException(new \RuntimeException('gone'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($this->stringContains('gone'));

        $observer = new PostDeleteAfter($this->urlBuilder(), $this->resource($connection), $this->config(), $logger);
        $observer->execute($this->observer($this->post(['url_key' => 'p'])));
    }
}
