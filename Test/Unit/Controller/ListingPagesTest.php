<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Controller;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Api\AuthorRepositoryInterface;
use Panth\Blog\Api\CategoryRepositoryInterface;
use Panth\Blog\Api\TagRepositoryInterface;
use Panth\Blog\Controller\Author\View as AuthorView;
use Panth\Blog\Controller\Category\View as CategoryView;
use Panth\Blog\Controller\Feed\Author as AuthorFeed;
use Panth\Blog\Controller\Index\Index;
use Panth\Blog\Controller\Index\Search;
use Panth\Blog\Controller\Tag\View as TagView;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Author;
use Panth\Blog\Model\Category;
use Panth\Blog\Model\Feed\FeedRepository;
use Panth\Blog\Model\Pagination\PageUrlBuilder;
use Panth\Blog\Model\StoreVisibility;
use Panth\Blog\Model\Tag;
use Panth\Blog\Model\Url\AuthorUrlBuilder;
use Panth\Blog\Model\Url\CategoryUrlBuilder;
use Panth\Blog\Model\Url\TagUrlBuilder;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use Panth\Blog\Test\Unit\Support\FrontendPageHelpers;
use PHPUnit\Framework\TestCase;

class ListingPagesTest extends TestCase
{
    use BlogTestHelpers;
    use FrontendPageHelpers;

    private function config(array $values = []): Config
    {
        $values += ['isEnabled' => true, 'getRouteFrontName' => 'blog', 'getIndexTitle' => '', 'getIndexDescription' => '', 'getTagThinThreshold' => 3];
        $config = $this->createStub(Config::class);
        foreach ($values as $m => $v) {
            $config->method($m)->willReturn($v);
        }
        return $config;
    }

    private function url(): UrlInterface
    {
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(static fn ($p) => 'https://s.test/' . $p);
        return $url;
    }

    private function storeManager(): StoreManagerInterface
    {
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturn('https://s.test/');
        $store->method('getId')->willReturn(1);
        $sm = $this->createStub(StoreManagerInterface::class);
        $sm->method('getStore')->willReturn($store);
        return $sm;
    }

    private function index(array $cfg = []): Index
    {
        return new Index(
            $this->frontPageFactory(),
            $this->frontRequest(),
            $this->frontRegistry(),
            $this->forwardFactory(),
            $this->config($cfg),
            $this->url(),
            $this->storeManager(),
            new PageUrlBuilder()
        );
    }

    public function testBlogIndexFirstPage(): void
    {
        $this->index()->execute();
        $this->assertSame('Blog', $this->page['title']);
        $this->assertNull($this->page['description']);
        $this->assertSame(1, $this->registered['panth_blog_current_page']);
        $this->assertSame('https://s.test/blog', $this->page['assets']['canonical']);
        $this->assertArrayNotHasKey('pb_pagination_prev', $this->page['assets']);
        $this->assertSame('https://s.test/blog/page/2', $this->page['assets']['pb_pagination_next']);
        $this->assertSame(['home', 'blog'], array_keys($this->page['crumbs']));
    }

    public function testBlogIndexLaterPageKeepsFirstPageCanonical(): void
    {
        $this->reqParams = ['page' => '3'];
        $this->index(['getIndexTitle' => ' Journal ', 'getIndexDescription' => 'All posts'])->execute();
        $this->assertSame('Journal', $this->page['title']);
        $this->assertSame('All posts', $this->page['description']);
        $this->assertSame('https://s.test/blog', $this->page['assets']['canonical']);
        $this->assertSame('https://s.test/blog/page/2', $this->page['assets']['pb_pagination_prev']);
        $this->assertSame('https://s.test/blog/page/4', $this->page['assets']['pb_pagination_next']);
    }

    public function testBlogIndexClampsPageAndHandlesDisabled(): void
    {
        $this->reqParams = ['page' => '-4'];
        $this->index()->execute();
        $this->assertSame(1, $this->registered['panth_blog_current_page']);

        $this->index(['isEnabled' => false])->execute();
        $this->assertSame('index', $this->forwardedTo);
    }

    private function search(array $cfg = []): Search
    {
        return new Search(
            $this->frontPageFactory(),
            $this->frontRequest(),
            $this->frontRegistry(),
            $this->forwardFactory(),
            $this->config($cfg),
            $this->url(),
            $this->storeManager()
        );
    }

    public function testSearchPage(): void
    {
        $this->reqParams = ['q' => '  hyva  ', 'page' => '2'];
        $this->search()->execute();
        $this->assertSame('Search results for "hyva"', $this->page['title']);
        $this->assertSame('noindex,follow', $this->page['robots']);
        $this->assertSame('hyva', $this->registered['panth_blog_search_query']);
        $this->assertSame(2, $this->registered['panth_blog_current_page']);
        $this->assertSame('Search: hyva', $this->page['crumbs']['blog_search']['label']);
        $this->assertSame('https://s.test/blog/search', $this->page['assets']['canonical']);
    }

    public function testEmptySearchAndDisabled(): void
    {
        $this->search()->execute();
        $this->assertSame('Search the blog', $this->page['title']);
        $this->assertSame('Search', $this->page['crumbs']['blog_search']['label']);

        $this->search(['isEnabled' => false])->execute();
        $this->assertSame('index', $this->forwardedTo);
    }

    private function categoryView(?Category $category, bool $visible = true, ?Category $parent = null, array $cfg = []): CategoryView
    {
        $repo = $this->createStub(CategoryRepositoryInterface::class);
        if ($category === null) {
            $repo->method('getByUrlKey')->willThrowException(new NoSuchEntityException());
        } else {
            $repo->method('getByUrlKey')->willReturn($category);
        }
        $repo->method('getById')->willReturn($parent ?? $this->makeModel(Category::class));
        $urls = $this->createStub(CategoryUrlBuilder::class);
        $urls->method('getCategoryUrl')->willReturnCallback(static fn ($c) => 'https://s.test/blog/category/' . $c->getUrlKey());
        $visibility = $this->createStub(StoreVisibility::class);
        $visibility->method('isCategoryVisible')->willReturn($visible);

        return new CategoryView(
            $this->frontPageFactory(),
            $this->frontRequest(),
            $this->frontRegistry(),
            $this->forwardFactory(),
            $this->config($cfg),
            $repo,
            $this->url(),
            $urls,
            new PageUrlBuilder(),
            $visibility
        );
    }

    public function testCategoryPage(): void
    {
        $category = $this->makeModel(Category::class, ['category_id' => 4, 'url_key' => 'news', 'name' => 'News', 'is_active' => 1, 'parent_id' => 2, 'meta_description' => 'About news']);
        $parent = $this->makeModel(Category::class, ['url_key' => 'root', 'name' => 'Root', 'is_active' => 1]);
        $this->reqParams = ['slug' => 'news', 'page' => '2'];

        $this->categoryView($category, true, $parent)->execute();

        $this->assertSame($category, $this->registered['current_panth_blog_category']);
        $this->assertSame('News', $this->page['title']);
        $this->assertSame('About news', $this->page['description']);
        $this->assertSame('index,follow', $this->page['robots']);
        $this->assertSame('https://s.test/blog/category/root', $this->page['crumbs']['blog_parent_category']['link']);
        $this->assertSame('https://s.test/blog/category/news', $this->page['assets']['pb_pagination_prev']);
        $this->assertSame('https://s.test/blog/category/news/page/3', $this->page['assets']['pb_pagination_next']);
    }

    public function testCategoryMetaTitleAndInactiveParent(): void
    {
        $category = $this->makeModel(Category::class, ['category_id' => 4, 'name' => 'News', 'meta_title' => 'News Meta', 'is_active' => 1, 'parent_id' => 2, 'meta_robots' => 'noindex,follow']);
        $this->reqParams = ['slug' => 'news'];
        $this->categoryView($category, true, $this->makeModel(Category::class, ['is_active' => 0]))->execute();
        $this->assertSame('News Meta', $this->page['title']);
        $this->assertSame('noindex,follow', $this->page['robots']);
        $this->assertArrayNotHasKey('blog_parent_category', $this->page['crumbs']);
    }

    public function testCategoryNotFoundCases(): void
    {
        $active = $this->makeModel(Category::class, ['category_id' => 4, 'is_active' => 1]);
        $this->reqParams = ['slug' => 'news'];
        foreach ([
            [null, true, []],
            [$this->makeModel(Category::class, ['is_active' => 0]), true, []],
            [$active, false, []],
            [$active, true, ['isEnabled' => false]],
        ] as $i => [$category, $visible, $cfg]) {
            $this->forwardedTo = null;
            $this->categoryView($category, $visible, null, $cfg)->execute();
            $this->assertSame('index', $this->forwardedTo, 'case ' . $i);
        }
    }

    private function tagView(?Tag $tag): TagView
    {
        $repo = $this->createStub(TagRepositoryInterface::class);
        if ($tag === null) {
            $repo->method('getByUrlKey')->willThrowException(new NoSuchEntityException());
        } else {
            $repo->method('getByUrlKey')->willReturn($tag);
        }
        $urls = $this->createStub(TagUrlBuilder::class);
        $urls->method('getTagUrl')->willReturn('https://s.test/blog/tag/php');
        return new TagView($this->frontPageFactory(), $this->frontRequest(), $this->frontRegistry(), $this->forwardFactory(), $this->config(), $repo, $this->url(), $urls, new PageUrlBuilder());
    }

    public function testThinTagIsNoindexed(): void
    {
        $this->reqParams = ['slug' => 'php'];
        $this->tagView($this->makeModel(Tag::class, ['name' => 'PHP', 'post_count' => 2]))->execute();
        $this->assertSame('PHP', $this->page['title']);
        $this->assertSame('noindex,follow', $this->page['robots']);
        $this->assertSame('Tag: PHP', $this->page['crumbs']['blog_tag']['label']);
        $this->assertSame('https://s.test/blog/tag/php', $this->page['assets']['canonical']);
    }

    public function testRichTagKeepsItsRobots(): void
    {
        $this->reqParams = ['slug' => 'php'];
        $this->tagView($this->makeModel(Tag::class, ['name' => 'PHP', 'post_count' => 9, 'description' => 'All PHP']))->execute();
        $this->assertSame('index,follow', $this->page['robots']);
        $this->assertSame('All PHP', $this->page['description']);
    }

    public function testMissingTagForwards(): void
    {
        $this->reqParams = ['slug' => 'php'];
        $this->tagView(null)->execute();
        $this->assertSame('index', $this->forwardedTo);
    }

    private function authorView(?Author $author): AuthorView
    {
        $repo = $this->createStub(AuthorRepositoryInterface::class);
        if ($author === null) {
            $repo->method('getByUrlKey')->willThrowException(new NoSuchEntityException());
        } else {
            $repo->method('getByUrlKey')->willReturn($author);
        }
        $urls = $this->createStub(AuthorUrlBuilder::class);
        $urls->method('getAuthorUrl')->willReturn('https://s.test/blog/author/jane');
        return new AuthorView($this->frontPageFactory(), $this->frontRequest(), $this->frontRegistry(), $this->forwardFactory(), $this->config(), $repo, $this->url(), $urls, new PageUrlBuilder());
    }

    public function testAuthorPage(): void
    {
        $this->reqParams = ['slug' => 'jane'];
        $this->authorView($this->makeModel(Author::class, ['display_name' => 'Jane', 'short_bio' => 'Writer', 'is_active' => 1]))->execute();
        $this->assertSame('Jane', $this->page['title']);
        $this->assertSame('Writer', $this->page['description']);
        $this->assertSame('index,follow', $this->page['robots']);
        $this->assertSame('Author: Jane', $this->page['crumbs']['blog_author']['label']);
        $this->assertSame('https://s.test/blog/author/jane/page/2', $this->page['assets']['pb_pagination_next']);
    }

    public function testInactiveOrMissingAuthorForwards(): void
    {
        $this->reqParams = ['slug' => 'jane'];
        $this->authorView($this->makeModel(Author::class, ['is_active' => 0]))->execute();
        $this->assertSame('index', $this->forwardedTo);

        $this->forwardedTo = null;
        $this->authorView(null)->execute();
        $this->assertSame('index', $this->forwardedTo);
    }

    public function testAuthorFeed(): void
    {
        $result = ['code' => 200, 'body' => null];
        $raw = $this->createStub(Raw::class);
        $raw->method('setHttpResponseCode')->willReturnCallback(function ($c) use (&$result, $raw) {
            $result['code'] = $c;
            return $raw;
        });
        $raw->method('setHeader')->willReturnSelf();
        $raw->method('setContents')->willReturnCallback(function ($b) use (&$result, $raw) {
            $result['body'] = $b;
            return $raw;
        });
        $factory = $this->createStub(ResultFactory::class);
        $factory->method('create')->willReturn($raw);

        $repo = $this->createStub(AuthorRepositoryInterface::class);
        $repo->method('getByUrlKey')->willReturnOnConsecutiveCalls(
            $this->makeModel(Author::class, ['author_id' => 2, 'is_active' => 1]),
            $this->makeModel(Author::class, ['author_id' => 2, 'is_active' => 0])
        );
        $feeds = $this->createStub(FeedRepository::class);
        $feeds->method('buildAuthorRss')->willReturn('<rss>jane</rss>');
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturn('jane');
        $config = $this->config(['isFeedEnabled' => true, 'isPerAuthorFeeds' => true]);

        $controller = new AuthorFeed($factory, $request, $config, $feeds, $repo, $this->storeManager());
        $controller->execute();
        $this->assertSame('<rss>jane</rss>', $result['body']);
        $controller->execute();
        $this->assertSame(404, $result['code']);

        $disabled = new AuthorFeed($factory, $request, $this->config(['isFeedEnabled' => true, 'isPerAuthorFeeds' => false]), $feeds, $repo, $this->storeManager());
        $result['code'] = 200;
        $disabled->execute();
        $this->assertSame(404, $result['code']);
    }
}
