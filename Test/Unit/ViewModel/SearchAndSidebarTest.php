<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\ViewModel;

use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Api\CategoryRepositoryInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Api\TagRepositoryInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Author;
use Panth\Blog\Model\Category;
use Panth\Blog\Model\Post;
use Panth\Blog\Model\StoreVisibility;
use Panth\Blog\Model\Tag;
use Panth\Blog\Model\Url\AuthorUrlBuilder;
use Panth\Blog\Model\Url\CategoryUrlBuilder;
use Panth\Blog\Model\Url\PostUrlBuilder;
use Panth\Blog\Model\Url\TagUrlBuilder;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use Panth\Blog\ViewModel\SearchResults;
use Panth\Blog\ViewModel\Sidebar;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SearchAndSidebarTest extends TestCase
{
    use BlogTestHelpers;

    private array $registryData = [];
    private array $reqParams = [];
    private array $quoted = [];
    private array $cfg = [];

    protected function setUp(): void
    {
        $this->cfg = ['getPostsPerPage' => 10, 'getRouteFrontName' => 'blog', 'getSidebarRecentCount' => 2, 'getTagThinThreshold' => 1,
            'isSidebarShowCategories' => true, 'isSidebarShowTagCloud' => false, 'isSidebarShowSearch' => true, 'isSidebarShowSubscribe' => false];
    }

    private function config(): Config
    {
        $config = $this->createStub(Config::class);
        foreach ($this->cfg as $m => $v) {
            $config->method($m)->willReturn($v);
        }
        return $config;
    }

    private function resource(AdapterInterface $connection): ResourceConnection
    {
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        return $resource;
    }

    private function postRepo(): PostRepositoryInterface
    {
        $repo = $this->createStub(PostRepositoryInterface::class);
        $repo->method('getById')->willReturnCallback(function (int $id) {
            if ($id === 99) {
                throw new NoSuchEntityException();
            }
            return $this->makeModel(Post::class, ['post_id' => $id, 'url_key' => 'p' . $id]);
        });
        return $repo;
    }

    private function storeManager(): StoreManagerInterface
    {
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturnCallback(static fn ($t = 'link') => $t === 'media' ? 'https://s.test/media/' : 'https://s.test/');
        $sm = $this->createStub(StoreManagerInterface::class);
        $sm->method('getStore')->willReturn($store);
        return $sm;
    }

    private function search(?AdapterInterface $connection = null): SearchResults
    {
        if ($connection === null) {
            $connection = $this->connectionStub();
            $connection->method('quote')->willReturnCallback(function ($v) {
                $this->quoted[] = $v;
                return "'" . $v . "'";
            });
            $connection->method('fetchAll')->willReturn([['post_id' => 4], ['post_id' => 99], ['post_id' => 5]]);
            $connection->method('fetchOne')->willReturn('12');
        }
        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturnCallback(fn ($k) => $this->registryData[$k] ?? null);
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnCallback(fn ($k, $d = null) => $this->reqParams[$k] ?? $d);
        $url = $this->createStub(UrlInterface::class);
        $url->method('getDirectUrl')->willReturnCallback(static function ($path, $params = []) {
            return 'https://s.test/' . $path . (isset($params['_query']) ? '?' . http_build_query($params['_query']) : '');
        });
        $postUrl = $this->createStub(PostUrlBuilder::class);
        $postUrl->method('getPostUrl')->willReturnCallback(static fn ($p) => 'https://s.test/blog/' . $p->getUrlKey());
        $visibility = $this->createStub(StoreVisibility::class);
        $visibility->method('filterPosts')->willReturnArgument(0);

        return new SearchResults(
            $registry,
            $this->resource($connection),
            $this->config(),
            $this->postRepo(),
            $request,
            $this->storeManager(),
            $url,
            $postUrl,
            $this->createStub(LoggerInterface::class),
            $visibility
        );
    }

    public function testQueryComesFromRegistryThenRequest(): void
    {
        $this->registryData['panth_blog_search_query'] = ' hyva ';
        $this->assertSame('hyva', $this->search()->getQuery());

        $this->registryData = [];
        $this->reqParams = ['q' => ' tailwind '];
        $this->assertSame('tailwind', $this->search()->getQuery());

        $this->reqParams = ['q' => ['array']];
        $this->assertSame('', $this->search()->getQuery());
    }

    public function testResultsEscapeLikeWildcards(): void
    {
        $this->reqParams = ['q' => '50%_off', 'page' => '2'];
        $search = $this->search();

        $results = $search->getResults();
        $this->assertSame(['p4', 'p5'], array_map(static fn ($p) => $p->getUrlKey(), $results));
        $this->assertContains('%50\\%\\_off%', $this->quoted);
        $this->assertSame($results, $search->getResults());
        $this->assertSame(12, $search->getTotalResults());
        $this->assertSame(12, $search->getTotalPosts());
        $this->assertSame(2, $search->getCurrentPage());
    }

    public function testEmptyQueryReturnsNothingWithoutQuerying(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->never())->method('select');
        $search = $this->search($connection);
        $this->assertSame([], $search->getResults());
        $this->assertSame(0, $search->getTotalResults());
    }

    public function testSearchQueryFailureDegrades(): void
    {
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willThrowException(new \RuntimeException('db'));
        $this->reqParams = ['q' => 'x'];
        $search = $this->search($connection);
        $this->assertSame([], $search->getResults());
        $this->assertSame(0, $search->getTotalResults());
    }

    public function testSearchUrls(): void
    {
        $this->reqParams = ['q' => 'a b'];
        $search = $this->search();
        $this->assertSame('https://s.test/blog/search?q=a+b', $search->getPageUrl(1));
        $this->assertSame('https://s.test/blog/search?q=a+b&page=3', $search->getPageUrl(3));
        $this->assertSame('https://s.test/blog/search', $search->getSearchActionUrl());
        $this->assertSame('https://s.test/media/blog/x.png', $search->getMediaUrl('x.png'));
        $this->assertSame('https://s.test/blog/p4', $search->getPostUrl($this->makeModel(Post::class, ['url_key' => 'p4'])));
        $this->assertSame(10, $search->getPostsPerPage());
    }

    private function sidebar(AdapterInterface $connection, ?LoggerInterface $logger = null): Sidebar
    {
        $postUrl = $this->createStub(PostUrlBuilder::class);
        $postUrl->method('getPostUrl')->willThrowException(new \RuntimeException('x'));
        $categoryUrl = $this->createStub(CategoryUrlBuilder::class);
        $categoryUrl->method('getCategoryUrl')->willReturn('https://s.test/blog/category/news');
        $tagUrl = $this->createStub(TagUrlBuilder::class);
        $tagUrl->method('getTagUrl')->willReturn('https://s.test/blog/tag/php');
        $authorUrl = $this->createStub(AuthorUrlBuilder::class);
        $authorUrl->method('getAuthorUrl')->willReturn('https://s.test/blog/author/jane');
        $visibility = $this->createStub(StoreVisibility::class);
        $visibility->method('filterPosts')->willReturnArgument(0);
        $visibility->method('filterCategories')->willReturnArgument(0);

        return new Sidebar(
            $this->config(),
            $this->postRepo(),
            $this->createStub(CategoryRepositoryInterface::class),
            $this->createStub(TagRepositoryInterface::class),
            $this->resource($connection),
            $this->storeManager(),
            $postUrl,
            $categoryUrl,
            $tagUrl,
            $authorUrl,
            $logger ?? $this->createStub(LoggerInterface::class),
            $visibility
        );
    }

    public function testSidebarFlagsAndUrlHelpers(): void
    {
        $sidebar = $this->sidebar($this->connectionStub());
        $this->assertTrue($sidebar->isShowCategories());
        $this->assertFalse($sidebar->isShowTagCloud());
        $this->assertTrue($sidebar->isShowSearch());
        $this->assertFalse($sidebar->isShowSubscribe());
        $this->assertTrue($sidebar->isShowRecent());
        $this->assertSame('blog', $sidebar->getRouteFrontName());
        $this->assertSame('', $sidebar->getPostUrl($this->makeModel(Post::class)));
        $this->assertSame('https://s.test/blog/category/news', $sidebar->getCategoryUrl($this->makeModel(Category::class)));
        $this->assertSame('https://s.test/blog/tag/php', $sidebar->getTagUrl($this->makeModel(Tag::class)));
        $this->assertSame('https://s.test/blog/author/jane', $sidebar->getAuthorUrl($this->makeModel(Author::class)));
    }

    public function testRecentPosts(): void
    {
        $connection = $this->connectionStub();
        $connection->method('fetchCol')->willReturn(['3', '99']);
        $sidebar = $this->sidebar($connection);
        $this->assertSame(['p3'], array_map(static fn ($p) => $p->getUrlKey(), $sidebar->getRecentPosts()));

        $this->cfg['getSidebarRecentCount'] = 0;
        $none = $this->createMock(AdapterInterface::class);
        $none->expects($this->never())->method('select');
        $this->assertSame([], $this->sidebar($none)->getRecentPosts());
        $this->assertFalse($this->sidebar($none)->isShowRecent());
    }

    public function testCategoryTree(): void
    {
        $connection = $this->connectionStub();
        $connection->method('fetchAll')->willReturn([
            ['category_id' => '1', 'parent_id' => null, 'level' => '1', 'url_key' => 'news', 'name' => 'News', 'post_count' => '4'],
            ['category_id' => '2', 'parent_id' => '1', 'level' => '2', 'url_key' => '', 'name' => 'Draft', 'post_count' => null],
        ]);
        $this->assertSame([
            ['id' => 1, 'name' => 'News', 'url' => 'https://s.test/blog/category/news', 'level' => 1, 'post_count' => 4],
            ['id' => 2, 'name' => 'Draft', 'url' => '', 'level' => 2, 'post_count' => 0],
        ], $this->sidebar($connection)->getCategoryTree());
    }

    public function testTagCloudScalesLogarithmically(): void
    {
        $connection = $this->connectionStub();
        $connection->method('fetchAll')->willReturn([
            ['tag_id' => 1, 'url_key' => 'php', 'name' => 'php', 'post_count' => 9],
            ['tag_id' => 2, 'url_key' => 'css', 'name' => 'css', 'post_count' => 1],
        ]);
        $cloud = $this->sidebar($connection)->getTagCloud();
        $this->assertSame('https://s.test/blog/tag/php', $cloud[0]['url']);
        $this->assertSame(1.0, $cloud[0]['scale']);
        $this->assertSame(round(log(2) / log(10), 3), $cloud[1]['scale']);
        $this->assertSame(1, $cloud[1]['count']);
    }

    public function testTagCloudEmptyAndFailures(): void
    {
        $empty = $this->connectionStub();
        $empty->method('fetchAll')->willReturn([]);
        $this->assertSame([], $this->sidebar($empty)->getTagCloud());

        $broken = $this->createStub(AdapterInterface::class);
        $broken->method('select')->willThrowException(new \RuntimeException('db'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(3))->method('warning');
        $sidebar = $this->sidebar($broken, $logger);
        $this->assertSame([], $sidebar->getTagCloud());
        $this->assertSame([], $sidebar->getCategoryTree());
        $this->assertSame([], $sidebar->getRecentPosts());
    }
}
