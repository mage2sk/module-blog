<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Controller\Post;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Panth\Blog\Api\AuthorRepositoryInterface;
use Panth\Blog\Api\CategoryRepositoryInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Controller\Post\View;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Author;
use Panth\Blog\Model\Category;
use Panth\Blog\Model\Post;
use Panth\Blog\Model\ResourceModel\Post as PostResource;
use Panth\Blog\Model\StoreVisibility;
use Panth\Blog\Model\Url\CategoryUrlBuilder;
use Panth\Blog\Model\Url\PostUrlBuilder;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use Panth\Blog\Test\Unit\Support\FrontendPageHelpers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ViewTest extends TestCase
{
    use BlogTestHelpers;
    use FrontendPageHelpers;

    private array $cfg = [];
    private ?Post $post = null;
    private bool $visible = true;
    private ?int $primaryId = null;

    protected function setUp(): void
    {
        $this->cfg = ['isEnabled' => true, 'getTitleTemplate' => '', 'getDefaultMetaDescription' => 'Default desc', 'getRouteFrontName' => 'blog'];
        $this->reqParams = ['slug' => 'hello'];
        $this->post = $this->makeModel(Post::class, [
            'post_id' => 3,
            'status' => 'published',
            'title' => 'Hello World',
            'author_id' => 2,
        ]);
    }

    private function dispatch(): void
    {
        $config = $this->createStub(Config::class);
        foreach ($this->cfg as $m => $v) {
            $config->method($m)->willReturn($v);
        }
        $posts = $this->createStub(PostRepositoryInterface::class);
        $posts->method('getByUrlKey')->willReturnCallback(function () {
            if ($this->post === null) {
                throw new NoSuchEntityException();
            }
            return $this->post;
        });
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(static fn ($p) => 'https://s.test/' . $p);
        $resource = $this->createStub(PostResource::class);
        $resource->method('getPrimaryCategoryId')->willReturnCallback(fn () => $this->primaryId);
        $categories = $this->createStub(CategoryRepositoryInterface::class);
        $categories->method('getById')->willReturn($this->makeModel(Category::class, ['name' => 'News']));
        $categoryUrls = $this->createStub(CategoryUrlBuilder::class);
        $categoryUrls->method('getCategoryUrl')->willReturn('https://s.test/blog/category/news');
        $postUrls = $this->createStub(PostUrlBuilder::class);
        $postUrls->method('getPostUrl')->willReturn('https://s.test/blog/hello');
        $authors = $this->createStub(AuthorRepositoryInterface::class);
        $authors->method('getById')->willReturn($this->makeModel(Author::class, ['display_name' => 'Jane']));
        $visibility = $this->createStub(StoreVisibility::class);
        $visibility->method('isPostVisible')->willReturn($this->visible);

        (new View(
            $this->frontPageFactory(),
            $this->frontRequest(),
            $this->frontRegistry(),
            $this->forwardFactory(),
            $config,
            $posts,
            $url,
            $resource,
            $categories,
            $categoryUrls,
            $postUrls,
            $authors,
            $visibility
        ))->execute();
    }

    public function testRendersPublishedPostWithFallbacks(): void
    {
        $this->dispatch();

        $this->assertNull($this->forwardedTo);
        $this->assertSame($this->post, $this->registered['current_panth_blog_post']);
        $this->assertSame('Hello World', $this->page['title']);
        $this->assertSame('Default desc', $this->page['description']);
        $this->assertSame('index,follow', $this->page['robots']);
        $this->assertSame('', $this->page['keywords']);
        $this->assertSame('https://s.test/blog/hello', $this->page['assets']['canonical']);
        $this->assertSame(['home', 'blog', 'blog_post'], array_keys($this->page['crumbs']));
    }

    public function testUsesPostMetaAndPrimaryCategory(): void
    {
        $this->post->addData([
            'meta_title' => 'Meta Title',
            'meta_description' => 'Meta desc',
            'meta_keywords' => 'a,b',
            'meta_robots' => 'noindex,nofollow',
            'canonical_url' => 'https://other.test/x',
        ]);
        $this->primaryId = 5;
        $this->dispatch();

        $this->assertSame('Meta Title', $this->page['title']);
        $this->assertSame('Meta desc', $this->page['description']);
        $this->assertSame('a,b', $this->page['keywords']);
        $this->assertSame('noindex,nofollow', $this->page['robots']);
        $this->assertSame('https://other.test/x', $this->page['assets']['canonical']);
        $this->assertSame('https://s.test/blog/category/news', $this->page['crumbs']['blog_category']['link']);
    }

    public function testShortDescriptionIsSecondChoice(): void
    {
        $this->post->setData('meta_description', '  ');
        $this->post->setData('short_description', 'Short one');
        $this->dispatch();
        $this->assertSame('Short one', $this->page['description']);
    }

    public static function templates(): array
    {
        return [
            'all tokens' => ['{{post.meta_title}} | {{post.category}} | {{post.author}}', 'Hello World | News | Jane'],
            'empty category collapses separators' => ['{{post.title}} | {{post.category}} | Store', 'Hello World | Store'],
            'plain template ignored' => ['Static Title', 'Hello World'],
        ];
    }

    #[DataProvider('templates')]
    public function testTitleTemplate(string $template, string $expected): void
    {
        $this->cfg['getTitleTemplate'] = $template;
        $this->primaryId = str_contains($expected, 'News') ? 5 : null;
        $this->dispatch();
        $this->assertSame($expected, $this->page['title']);
    }

    public function testTemplateRenderingToNothingFallsBackToTitle(): void
    {
        $this->cfg['getTitleTemplate'] = '{{post.category}} | {{post.author}}';
        $this->post->setData('author_id', null);
        $this->dispatch();
        $this->assertSame('Hello World', $this->page['title']);
    }

    public function testNotFoundCases(): void
    {
        $this->cfg['isEnabled'] = false;
        $this->dispatch();
        $this->assertSame('index', $this->forwardedTo);

        $this->setUp();
        $this->forwardedTo = null;
        $this->reqParams = ['slug' => ' '];
        $this->dispatch();
        $this->assertSame('index', $this->forwardedTo);

        $this->setUp();
        $this->forwardedTo = null;
        $this->post = null;
        $this->dispatch();
        $this->assertSame('index', $this->forwardedTo);

        $this->setUp();
        $this->forwardedTo = null;
        $this->post->setStatus('draft');
        $this->dispatch();
        $this->assertSame('index', $this->forwardedTo);

        $this->setUp();
        $this->forwardedTo = null;
        $this->visible = false;
        $this->dispatch();
        $this->assertSame('index', $this->forwardedTo);
        $this->assertSame([], $this->registered);
    }

    public function testMissingBreadcrumbsBlockIsTolerated(): void
    {
        $this->withBreadcrumbs = false;
        $this->dispatch();
        $this->assertSame([], $this->page['crumbs']);
        $this->assertSame('Hello World', $this->page['title']);
    }
}
