<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Controller\Feed;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Api\CategoryRepositoryInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Api\TagRepositoryInterface;
use Panth\Blog\Controller\Feed\Atom;
use Panth\Blog\Controller\Feed\Category as CategoryFeed;
use Panth\Blog\Controller\Feed\Index;
use Panth\Blog\Controller\Feed\Tag as TagFeed;
use Panth\Blog\Controller\Markdown\Export;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Category;
use Panth\Blog\Model\Feed\FeedRepository;
use Panth\Blog\Model\Post;
use Panth\Blog\Model\StoreVisibility;
use Panth\Blog\Model\Tag;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\TestCase;

class FeedControllersTest extends TestCase
{
    use BlogTestHelpers;

    private array $raw = [];

    private function resultFactory(): ResultFactory
    {
        $this->raw = ['code' => 200, 'headers' => [], 'body' => null];
        $raw = $this->createStub(Raw::class);
        $raw->method('setHttpResponseCode')->willReturnCallback(function ($c) use ($raw) {
            $this->raw['code'] = $c;
            return $raw;
        });
        $raw->method('setHeader')->willReturnCallback(function ($n, $v) use ($raw) {
            $this->raw['headers'][$n] = $v;
            return $raw;
        });
        $raw->method('setContents')->willReturnCallback(function ($b) use ($raw) {
            $this->raw['body'] = $b;
            return $raw;
        });
        $factory = $this->createStub(ResultFactory::class);
        $factory->method('create')->willReturn($raw);
        return $factory;
    }

    private function config(array $flags): Config
    {
        $config = $this->createStub(Config::class);
        foreach (['isEnabled', 'isFeedEnabled', 'isPerCategoryFeeds', 'isPerTagFeeds', 'isMarkdownExportEnabled'] as $m) {
            $config->method($m)->willReturn($flags[$m] ?? true);
        }
        return $config;
    }

    private function storeManager(): StoreManagerInterface
    {
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(2);
        $sm = $this->createStub(StoreManagerInterface::class);
        $sm->method('getStore')->willReturn($store);
        return $sm;
    }

    private function request(string $slug): Http
    {
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturn($slug);
        return $request;
    }

    public function testSiteWideFeeds(): void
    {
        $feeds = $this->createStub(FeedRepository::class);
        $feeds->method('buildSiteWideRss')->willReturn('<rss/>');
        $feeds->method('buildSiteWideAtom')->willReturn('<feed/>');

        (new Index($this->resultFactory(), $this->config([]), $feeds, $this->storeManager()))->execute();
        $this->assertSame('<rss/>', $this->raw['body']);
        $this->assertSame('application/rss+xml; charset=utf-8', $this->raw['headers']['Content-Type']);

        (new Atom($this->resultFactory(), $this->config([]), $feeds, $this->storeManager()))->execute();
        $this->assertSame('<feed/>', $this->raw['body']);
        $this->assertSame('application/atom+xml; charset=utf-8', $this->raw['headers']['Content-Type']);
    }

    public function testSiteWideFeedsReturn404WhenDisabled(): void
    {
        $feeds = $this->createMock(FeedRepository::class);
        $feeds->expects($this->never())->method('buildSiteWideRss');
        $feeds->expects($this->never())->method('buildSiteWideAtom');

        (new Index($this->resultFactory(), $this->config(['isFeedEnabled' => false]), $feeds, $this->storeManager()))->execute();
        $this->assertSame(404, $this->raw['code']);
        (new Atom($this->resultFactory(), $this->config(['isEnabled' => false]), $feeds, $this->storeManager()))->execute();
        $this->assertSame(404, $this->raw['code']);
        $this->assertSame('', $this->raw['body']);
    }

    private function categoryController(array $flags, string $slug, ?Category $category, bool $visible, ?FeedRepository $feeds = null): CategoryFeed
    {
        $repo = $this->createStub(CategoryRepositoryInterface::class);
        if ($category === null) {
            $repo->method('getByUrlKey')->willThrowException(new NoSuchEntityException());
        } else {
            $repo->method('getByUrlKey')->willReturn($category);
        }
        $visibility = $this->createStub(StoreVisibility::class);
        $visibility->method('isCategoryVisible')->willReturn($visible);
        return new CategoryFeed(
            $this->resultFactory(),
            $this->request($slug),
            $this->config($flags),
            $feeds ?? $this->createStub(FeedRepository::class),
            $repo,
            $this->storeManager(),
            $visibility
        );
    }

    public function testCategoryFeedServesActiveVisibleCategory(): void
    {
        $feeds = $this->createMock(FeedRepository::class);
        $feeds->expects($this->once())->method('buildCategoryRss')->with(5, 2)->willReturn('<rss>cat</rss>');
        $category = $this->makeModel(Category::class, ['category_id' => 5, 'is_active' => 1]);

        $this->categoryController([], 'news', $category, true, $feeds)->execute();
        $this->assertSame(200, $this->raw['code']);
        $this->assertSame('<rss>cat</rss>', $this->raw['body']);
    }

    public function testCategoryFeedNotFoundCases(): void
    {
        $active = $this->makeModel(Category::class, ['category_id' => 5, 'is_active' => 1]);
        $inactive = $this->makeModel(Category::class, ['category_id' => 5, 'is_active' => 0]);
        $cases = [
            [['isPerCategoryFeeds' => false], 'news', $active, true],
            [[], '', $active, true],
            [[], 'news', null, true],
            [[], 'news', $inactive, true],
            [[], 'news', $active, false],
        ];
        foreach ($cases as $i => [$flags, $slug, $category, $visible]) {
            $this->categoryController($flags, $slug, $category, $visible)->execute();
            $this->assertSame(404, $this->raw['code'], 'case ' . $i);
        }
    }

    public function testTagFeed(): void
    {
        $repo = $this->createStub(TagRepositoryInterface::class);
        $repo->method('getByUrlKey')->willReturnOnConsecutiveCalls(
            $this->makeModel(Tag::class, ['tag_id' => 8]),
            $this->makeModel(Tag::class),
            $this->throwException(new NoSuchEntityException())
        );
        $feeds = $this->createStub(FeedRepository::class);
        $feeds->method('buildTagRss')->willReturn('<rss>tag</rss>');

        $controller = fn (array $flags = [], string $slug = 'php') => new TagFeed($this->resultFactory(), $this->request($slug), $this->config($flags), $feeds, $repo, $this->storeManager());

        $controller()->execute();
        $this->assertSame('<rss>tag</rss>', $this->raw['body']);
        $controller()->execute();
        $this->assertSame(404, $this->raw['code']);
        $controller()->execute();
        $this->assertSame(404, $this->raw['code']);
        $controller(['isPerTagFeeds' => false])->execute();
        $this->assertSame(404, $this->raw['code']);
        $controller([], ' ')->execute();
        $this->assertSame(404, $this->raw['code']);
    }

    private function export(array $flags, string $slug, ?Post $post, bool $visible = true): Export
    {
        $repo = $this->createStub(PostRepositoryInterface::class);
        if ($post === null) {
            $repo->method('getByUrlKey')->willThrowException(new NoSuchEntityException());
        } else {
            $repo->method('getByUrlKey')->willReturn($post);
        }
        $visibility = $this->createStub(StoreVisibility::class);
        $visibility->method('isPostVisible')->willReturn($visible);
        return new Export($this->resultFactory(), $this->request($slug), $this->config($flags), $repo, $visibility);
    }

    public function testMarkdownExportOfPublishedPost(): void
    {
        $post = $this->makeModel(Post::class, [
            'status' => 'published',
            'title' => 'Hello',
            'url_key' => 'hello',
            'short_description' => 'Short',
            'content' => '<h3>Part</h3><p><i>x</i> &lt;y&gt;</p>',
        ]);
        $this->export([], 'hello', $post)->execute();

        $this->assertSame('text/markdown; charset=utf-8', $this->raw['headers']['Content-Type']);
        $this->assertSame("---\ntitle: \"Hello\"\nurl_key: hello\npublished_at: \n---\n\n> Short\n\n### Part\n\n*x* <y>\n", $this->raw['body']);
    }

    public function testMarkdownExportNotFoundCases(): void
    {
        $published = $this->makeModel(Post::class, ['status' => 'published']);
        $draft = $this->makeModel(Post::class, ['status' => 'draft']);
        foreach ([
            [['isMarkdownExportEnabled' => false], 'a', $published, true],
            [[], '', $published, true],
            [[], 'a', null, true],
            [[], 'a', $draft, true],
            [[], 'a', $published, false],
        ] as $i => [$flags, $slug, $post, $visible]) {
            $this->export($flags, $slug, $post, $visible)->execute();
            $this->assertSame(404, $this->raw['code'], 'case ' . $i);
            $this->assertSame("Not Found\n", $this->raw['body']);
            $this->assertSame('no-store, max-age=0', $this->raw['headers']['Cache-Control']);
        }
    }
}
