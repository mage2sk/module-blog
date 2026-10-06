<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\ViewModel;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Api\AuthorRepositoryInterface;
use Panth\Blog\Api\CategoryRepositoryInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Api\TagRepositoryInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Author;
use Panth\Blog\Model\Category;
use Panth\Blog\Model\Post;
use Panth\Blog\Model\Schema\JsonLdRenderer;
use Panth\Blog\Model\StoreVisibility;
use Panth\Blog\Model\Tag;
use Panth\Blog\Model\Url\AuthorUrlBuilder;
use Panth\Blog\Model\Url\CategoryUrlBuilder;
use Panth\Blog\Model\Url\PostUrlBuilder;
use Panth\Blog\Model\Url\TagUrlBuilder;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use Panth\Blog\ViewModel\AuthorView;
use Panth\Blog\ViewModel\BlogIndex;
use Panth\Blog\ViewModel\CategoryView;
use Panth\Blog\ViewModel\TagView;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ListingViewModelsTest extends TestCase
{
    use BlogTestHelpers;

    private array $registryData = [];
    private array $reqParams = [];
    private array $cfg = [];
    private ?AdapterInterface $connection = null;
    private ?JsonLdRenderer $jsonLd = null;
    private array $limits = [];

    protected function setUp(): void
    {
        $this->cfg = ['getPostsPerPage' => 2, 'getRouteFrontName' => 'blog', 'getIndexTitle' => '', 'getIndexHeroText' => 'Hero', 'getIndexDescription' => 'Desc', 'getCategoryTemplate' => 'grid', 'isShowReadingTime' => true];
        $select = $this->createStub(\Magento\Framework\DB\Select::class);
        foreach (['from', 'where', 'order', 'join'] as $m) {
            $select->method($m)->willReturnSelf();
        }
        $select->method('limit')->willReturnCallback(function ($count, $offset = 0) use ($select) {
            $this->limits[] = [$count, $offset];
            return $select;
        });
        $this->connection = $this->createStub(AdapterInterface::class);
        $this->connection->method('select')->willReturn($select);
        $this->connection->method('fetchCol')->willReturn(['11', '12', '13']);
        $this->connection->method('fetchOne')->willReturn('7');
    }

    private function deps(): array
    {
        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturnCallback(fn ($k) => $this->registryData[$k] ?? null);
        $config = $this->createStub(Config::class);
        foreach ($this->cfg as $m => $v) {
            $config->method($m)->willReturn($v);
        }
        $posts = $this->createStub(PostRepositoryInterface::class);
        $posts->method('getById')->willReturnCallback(function (int $id) {
            if ($id === 13) {
                throw new NoSuchEntityException();
            }
            return $this->makeModel(Post::class, ['post_id' => $id, 'title' => 'P' . $id, 'url_key' => 'p' . $id]);
        });
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturnCallback(static fn ($t = 'link') => $t === 'media' ? 'https://s.test/media/' : 'https://s.test/');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $postUrl = $this->createStub(PostUrlBuilder::class);
        $postUrl->method('getPostUrl')->willReturnCallback(static fn ($p) => 'https://s.test/blog/' . $p->getUrlKey());
        $postUrl->method('getPaginatedIndexUrl')->willReturnCallback(static fn ($n) => 'https://s.test/blog/page/' . $n);
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnCallback(fn ($k, $d = null) => $this->reqParams[$k] ?? $d);
        $visibility = $this->createStub(StoreVisibility::class);
        $visibility->method('filterPosts')->willReturnArgument(0);

        return [
            'registry' => $registry,
            'config' => $config,
            'posts' => $posts,
            'resource' => $resource,
            'storeManager' => $storeManager,
            'postUrl' => $postUrl,
            'request' => $request,
            'visibility' => $visibility,
            'jsonLd' => $this->jsonLd ?? $this->createStub(JsonLdRenderer::class),
            'logger' => $this->createStub(LoggerInterface::class),
        ];
    }

    private function blogIndex(): BlogIndex
    {
        $d = $this->deps();
        return new BlogIndex($d['registry'], $d['config'], $d['posts'], $d['resource'], $d['jsonLd'], $d['storeManager'], $d['postUrl'], $d['logger'], $d['visibility']);
    }

    public function testBlogIndexPagingAndPosts(): void
    {
        $this->registryData['panth_blog_current_page'] = '3';
        $index = $this->blogIndex();

        $this->assertSame(3, $index->getCurrentPage());
        $this->assertSame(['P11', 'P12'], array_map(static fn ($p) => $p->getTitle(), $index->getPosts()));
        $this->assertSame([[2, 4]], $this->limits);
        $this->assertSame(7, $index->getTotalPosts());
        $this->assertSame('Blog', $index->getIndexTitle());
        $this->assertSame('Hero', $index->getHeroText());
        $this->assertSame('https://s.test/blog/page/2', $index->getPageUrl(2));
        $this->assertTrue($index->isShowReadingTime());
    }

    public function testBlogIndexCurrentPageFallbacks(): void
    {
        $this->registryData['panth_blog_current_page'] = 'abc';
        $this->assertSame(1, $this->blogIndex()->getCurrentPage());
        $this->registryData['panth_blog_current_page'] = -2;
        $this->assertSame(1, $this->blogIndex()->getCurrentPage());
    }

    public function testMediaUrlsArePrefixedWithBlogFolder(): void
    {
        $index = $this->blogIndex();
        $this->assertSame('', $index->getMediaUrl(''));
        $this->assertSame('https://cdn.test/a.png', $index->getMediaUrl('https://cdn.test/a.png'));
        $this->assertSame('https://s.test/media/blog/2026/a.png', $index->getMediaUrl('/2026/a.png'));
    }

    public function testBlogIndexJsonLdItemPositionsFollowPage(): void
    {
        $this->registryData['panth_blog_current_page'] = 2;
        $this->jsonLd = $this->createMock(JsonLdRenderer::class);
        $this->jsonLd->expects($this->once())->method('renderForBlogIndex')
            ->willReturnCallback(function ($name, $url, $desc, $items, $crumbs) {
                $this->assertSame('Blog', $name);
                $this->assertSame('https://s.test/blog', $url);
                $this->assertSame('Desc', $desc);
                $this->assertSame([3, 4], array_column($items, 'position'));
                $this->assertSame('https://s.test/blog/p11', $items[0]['url']);
                $this->assertSame([['name' => 'Home', 'item' => 'https://s.test/'], ['name' => 'Blog', 'item' => 'https://s.test/blog']], $crumbs);
                return '{}';
            });
        $this->assertSame('{}', $this->blogIndex()->getJsonLd());
    }

    public function testBlogIndexQueryFailuresDegrade(): void
    {
        $this->connection = $this->createStub(AdapterInterface::class);
        $this->connection->method('select')->willThrowException(new \RuntimeException('db'));
        $index = $this->blogIndex();
        $this->assertSame([], $index->getPosts());
        $this->assertSame(0, $index->getTotalPosts());
    }

    private function categoryView(): CategoryView
    {
        $d = $this->deps();
        $urls = $this->createStub(CategoryUrlBuilder::class);
        $urls->method('getCategoryUrl')->willReturn('https://s.test/blog/category/news');
        $urls->method('getPaginatedCategoryUrl')->willReturnCallback(static fn ($c, $n) => 'https://s.test/blog/category/news/page/' . $n);
        return new CategoryView(
            $d['registry'],
            $d['config'],
            $this->createStub(CategoryRepositoryInterface::class),
            $d['posts'],
            $d['jsonLd'],
            $d['storeManager'],
            $d['resource'],
            $d['request'],
            $this->createStub(SearchCriteriaBuilder::class),
            $this->createStub(SortOrderBuilder::class),
            $urls,
            $d['postUrl'],
            $d['logger'],
            $d['visibility']
        );
    }

    public function testCategoryViewWithoutCategory(): void
    {
        $view = $this->categoryView();
        $this->assertNull($view->getCategory());
        $this->assertSame([], $view->getPosts());
        $this->assertSame(0, $view->getTotalPosts());
        $this->assertSame('', $view->getPageUrl(2));
        $this->assertSame('', $view->getJsonLd());
        $this->assertSame('', $view->getCanonicalUrl());
    }

    public function testCategoryViewUsesPerCategoryPageSizeAndRequestPage(): void
    {
        $this->registryData['current_panth_blog_category'] = $this->makeModel(Category::class, ['category_id' => 4, 'name' => 'News', 'posts_per_page' => 5]);
        $this->reqParams = ['p' => '2'];
        $view = $this->categoryView();

        $this->assertSame(5, $view->getPostsPerPage());
        $this->assertSame(2, $view->getCurrentPage());
        $this->assertCount(2, $view->getPosts());
        $view->getPosts();
        $this->assertSame([[5, 5]], $this->limits);
        $this->assertSame(7, $view->getTotalPosts());
        $this->assertSame('https://s.test/blog/category/news/page/3', $view->getPageUrl(3));
        $this->assertSame('https://s.test/blog/category/news', $view->getCanonicalUrl());
    }

    public function testCategoryViewInvalidRequestPage(): void
    {
        $this->reqParams = ['page' => 'x'];
        $this->assertSame(1, $this->categoryView()->getCurrentPage());
    }

    public function testEffectiveTemplate(): void
    {
        $this->registryData['current_panth_blog_category'] = $this->makeModel(Category::class, ['template' => 'Magazine']);
        $this->assertSame('magazine', $this->categoryView()->getEffectiveTemplate());

        $this->registryData['current_panth_blog_category'] = $this->makeModel(Category::class, ['template' => 'bogus']);
        $this->cfg['getCategoryTemplate'] = 'list';
        $this->assertSame('list', $this->categoryView()->getEffectiveTemplate());

        $this->cfg['getCategoryTemplate'] = 'weird';
        $this->assertSame('grid', $this->categoryView()->getEffectiveTemplate());
    }

    public function testExplicitGridCategoryCannotOverrideGlobalList(): void
    {
        $this->cfg['getCategoryTemplate'] = 'list';
        $this->registryData['current_panth_blog_category'] = $this->makeModel(Category::class, ['template' => 'grid']);
        $this->assertSame('list', $this->categoryView()->getEffectiveTemplate());
    }

    public function testCategoryJsonLd(): void
    {
        $this->registryData['current_panth_blog_category'] = $this->makeModel(Category::class, ['category_id' => 4, 'name' => 'News']);
        $this->jsonLd = $this->createMock(JsonLdRenderer::class);
        $this->jsonLd->expects($this->once())->method('renderForCategory')
            ->willReturnCallback(function ($cat, $url, $items, $crumbs, $total) {
                $this->assertSame('https://s.test/blog/category/news', $url);
                $this->assertSame([1, 2], array_column($items, 'position'));
                $this->assertSame('News', $crumbs[2]['name']);
                $this->assertSame(7, $total);
                return '{"c":1}';
            });
        $this->assertSame('{"c":1}', $this->categoryView()->getJsonLd());
    }

    private function authorView(): AuthorView
    {
        $d = $this->deps();
        $urls = $this->createStub(AuthorUrlBuilder::class);
        $urls->method('getAuthorUrl')->willReturn('https://s.test/blog/author/jane');
        $urls->method('getPaginatedAuthorUrl')->willReturnCallback(static fn ($a, $n) => 'https://s.test/blog/author/jane/page/' . $n);
        return new AuthorView(
            $d['registry'],
            $d['config'],
            $this->createStub(AuthorRepositoryInterface::class),
            $d['posts'],
            $d['resource'],
            $d['request'],
            $d['jsonLd'],
            $urls,
            $d['postUrl'],
            $d['storeManager'],
            $d['logger'],
            $d['visibility']
        );
    }

    public function testAuthorViewPostsAndLinks(): void
    {
        $this->registryData['current_panth_blog_author'] = $this->makeModel(Author::class, [
            'author_id' => 2,
            'display_name' => 'Jane',
            'links' => json_encode(['LinkedIn' => ' https://linkedin.test/jane ', 'github' => '', 'myspace' => 'https://x', 'x' => 'https://x.test/jane']),
        ]);
        $view = $this->authorView();

        $this->assertCount(2, $view->getPosts());
        $this->assertSame(7, $view->getTotalPosts());
        $this->assertSame('https://s.test/blog/author/jane/page/2', $view->getPageUrl(2));
        $this->assertSame(['linkedin' => 'https://linkedin.test/jane', 'x' => 'https://x.test/jane'], $view->getSocialLinks());
        $this->assertSame('https://s.test/media/blog/a.png', $view->getMediaUrl('a.png'));
    }

    public function testAuthorViewWithoutAuthorOrLinks(): void
    {
        $view = $this->authorView();
        $this->assertSame([], $view->getPosts());
        $this->assertSame(0, $view->getTotalPosts());
        $this->assertSame([], $view->getSocialLinks());
        $this->assertSame('', $view->getJsonLd());
        $this->assertSame('', $view->getPageUrl(2));

        $this->registryData['current_panth_blog_author'] = $this->makeModel(Author::class, ['author_id' => 2, 'links' => 'nope']);
        $this->assertSame([], $this->authorView()->getSocialLinks());
    }

    public function testAuthorJsonLd(): void
    {
        $author = $this->makeModel(Author::class, ['author_id' => 2, 'display_name' => 'Jane']);
        $this->registryData['current_panth_blog_author'] = $author;
        $this->jsonLd = $this->createMock(JsonLdRenderer::class);
        $this->jsonLd->expects($this->once())->method('renderForAuthor')
            ->with($author, [
                ['name' => 'Home', 'item' => 'https://s.test/'],
                ['name' => 'Blog', 'item' => 'https://s.test/blog'],
                ['name' => 'Jane', 'item' => 'https://s.test/blog/author/jane'],
            ])
            ->willReturn('{"a":1}');
        $this->assertSame('{"a":1}', $this->authorView()->getJsonLd());
    }

    private function tagView(): TagView
    {
        $d = $this->deps();
        $urls = $this->createStub(TagUrlBuilder::class);
        $urls->method('getTagUrl')->willReturn('https://s.test/blog/tag/php');
        $urls->method('getPaginatedTagUrl')->willReturnCallback(static fn ($t, $n) => 'https://s.test/blog/tag/php/page/' . $n);
        return new TagView(
            $d['registry'],
            $d['config'],
            $this->createStub(TagRepositoryInterface::class),
            $d['posts'],
            $d['resource'],
            $d['request'],
            $d['storeManager'],
            $d['jsonLd'],
            $urls,
            $d['postUrl'],
            $d['logger'],
            $d['visibility']
        );
    }

    public function testTagView(): void
    {
        $tag = $this->makeModel(Tag::class, ['tag_id' => 3, 'name' => 'PHP', 'description' => 'About PHP']);
        $this->registryData['current_panth_blog_tag'] = $tag;
        $this->registryData['panth_blog_current_page'] = 2;
        $this->jsonLd = $this->createMock(JsonLdRenderer::class);
        $this->jsonLd->expects($this->once())->method('renderForTag')
            ->willReturnCallback(function ($name, $url, $desc, $items, $total, $crumbs) {
                $this->assertSame(['PHP', 'https://s.test/blog/tag/php', 'About PHP', 7], [$name, $url, $desc, $total]);
                $this->assertSame([3, 4], array_column($items, 'position'));
                return '{"t":1}';
            });
        $view = $this->tagView();

        $this->assertSame($tag, $view->getTag());
        $this->assertSame('{"t":1}', $view->getJsonLd());
        $this->assertSame('https://s.test/blog/tag/php', $view->getCanonicalUrl());
        $this->assertSame('https://s.test/blog/tag/php/page/5', $view->getPageUrl(5));
        $this->assertSame('https://s.test/blog/tag/php', $view->getTagUrl($tag));
    }

    public function testTagViewWithoutTag(): void
    {
        $view = $this->tagView();
        $this->assertSame([], $view->getPosts());
        $this->assertSame(0, $view->getTotalPosts());
        $this->assertSame('', $view->getJsonLd());
        $this->assertSame('', $view->getCanonicalUrl());
    }
}
