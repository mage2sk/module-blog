<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\ViewModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Post;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use Panth\Blog\ViewModel\PostBadges;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PostBadgesTest extends TestCase
{
    use BlogTestHelpers;

    private function badges(AdapterInterface $connection, array $cfg = [], ?LoggerInterface $logger = null): PostBadges
    {
        $cfg += ['isShowBadges' => true, 'isShowFeaturedBadge' => true, 'getNewBadgeDays' => 7, 'getRouteFrontName' => 'blog'];
        $config = $this->createStub(Config::class);
        foreach ($cfg as $m => $v) {
            $config->method($m)->willReturn($v);
        }
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturn('https://s.test/');
        $sm = $this->createStub(StoreManagerInterface::class);
        $sm->method('getStore')->willReturn($store);
        return new PostBadges($resource, $sm, $this->createStub(UrlInterface::class), $config, $logger ?? $this->createStub(LoggerInterface::class));
    }

    private function post(array $data): Post
    {
        $post = $this->makeModel(Post::class, $data);
        $post->setId($data['id'] ?? 5);
        return $post;
    }

    public function testAllBadges(): void
    {
        $connection = $this->connectionStub();
        $connection->method('fetchRow')->willReturnOnConsecutiveCalls(
            ['name' => 'News', 'url_key' => 'news'],
            ['name' => 'php', 'url_key' => '/php/']
        );
        $post = $this->post(['is_featured' => 1, 'published_at' => gmdate('Y-m-d H:i:s', time() - 86400)]);

        $this->assertSame([
            ['label' => 'Featured', 'url' => '', 'tone' => 'featured'],
            ['label' => 'New', 'url' => '', 'tone' => 'new'],
            ['label' => 'News', 'url' => 'https://s.test/blog/category/news', 'tone' => 'category'],
            ['label' => 'php', 'url' => 'https://s.test/blog/tag/php', 'tone' => 'tag'],
        ], $this->badges($connection)->getBadgesForPost($post));
    }

    public function testOldUnfeaturedPostWithoutTaxonomy(): void
    {
        $connection = $this->connectionStub();
        $connection->method('fetchRow')->willReturn(false);
        $post = $this->post(['is_featured' => 0, 'published_at' => '2001-01-01 00:00:00']);
        $this->assertSame([], $this->badges($connection)->getBadgesForPost($post));
    }

    public function testDisabledOrInvalidInputs(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->never())->method('fetchRow');
        $this->assertSame([], $this->badges($connection, ['isShowBadges' => false])->getBadgesForPost($this->post([])));
        $this->assertSame([], $this->badges($connection)->getBadgesForPost('not an object'));
        $this->assertSame([], $this->badges($connection)->getBadgesForPost($this->post(['id' => 0])));
    }

    public function testFeaturedAndNewBadgesCanBeTurnedOff(): void
    {
        $connection = $this->connectionStub();
        $connection->method('fetchRow')->willReturn(false);
        $post = $this->post(['is_featured' => 1, 'published_at' => gmdate('Y-m-d H:i:s')]);
        $this->assertSame([], $this->badges($connection, ['isShowFeaturedBadge' => false, 'getNewBadgeDays' => 0])->getBadgesForPost($post));
    }

    public function testResultsAreCachedPerPost(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($this->selectStub());
        $connection->expects($this->exactly(2))->method('fetchRow')->willReturn(false);
        $badges = $this->badges($connection);
        $post = $this->post([]);
        $badges->getBadgesForPost($post);
        $badges->getBadgesForPost($post);
    }

    public function testQueryFailureIsLoggedAndPartialBadgesKept(): void
    {
        $connection = $this->connectionStub();
        $connection->method('fetchRow')->willThrowException(new \RuntimeException('sql'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($this->stringContains('post 5: sql'));
        $result = $this->badges($connection, [], $logger)->getBadgesForPost($this->post(['is_featured' => 1]));
        $this->assertSame(['featured'], array_column($result, 'tone'));
    }
}
