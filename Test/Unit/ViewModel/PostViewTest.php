<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\ViewModel;

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
use Panth\Blog\Model\Reader\ReadingTimeCalculator;
use Panth\Blog\Model\ResourceModel\Post as PostResource;
use Panth\Blog\Model\Schema\JsonLdRenderer;
use Panth\Blog\Model\StoreVisibility;
use Panth\Blog\Model\Tag;
use Panth\Blog\Model\Url\AuthorUrlBuilder;
use Panth\Blog\Model\Url\CategoryUrlBuilder;
use Panth\Blog\Model\Url\PostUrlBuilder;
use Panth\Blog\Model\Url\TagUrlBuilder;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use Panth\Blog\ViewModel\PostView;
use Panth\Blog\ViewModel\RelatedPosts;
use Panth\Blog\ViewModel\Toc;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PostViewTest extends TestCase
{
    use BlogTestHelpers;

    private array $cfg = [];
    private array $categories = [];
    private array $tags = [];
    private array $posts = [];
    private array $resourceData = [];
    private array $invisibleCategories = [];
    private array $invisiblePosts = [];
    private ?AdapterInterface $connection = null;
    private ?JsonLdRenderer $jsonLd = null;
    private ?LoggerInterface $logger = null;

    protected function setUp(): void
    {
        $this->cfg = [
            'getTitleTemplate' => '',
            'getDefaultMetaDescription' => 'Default',
            'getRelatedPostsCount' => 0,
            'getReadingSpeedWpm' => 100,
            'isShowToc' => true,
            'getTocMinH2' => 2,
            'getRouteFrontName' => 'blog',
            'isShowReadingTime' => true,
            'isShowAuthorBio' => false,
            'isShowShareButtons' => true,
        ];
        $this->resourceData = ['primary' => null, 'categories' => [], 'tags' => [], 'related' => []];
    }

    private function view(?Post $post): PostView
    {
        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturn($post);

        $config = $this->createStub(Config::class);
        foreach ($this->cfg as $m => $v) {
            $config->method($m)->willReturn($v);
        }

        $postRepo = $this->createStub(PostRepositoryInterface::class);
        $postRepo->method('getById')->willReturnCallback(function (int $id) {
            if (!isset($this->posts[$id])) {
                throw new NoSuchEntityException();
            }
            return $this->posts[$id];
        });
        $categoryRepo = $this->createStub(CategoryRepositoryInterface::class);
        $categoryRepo->method('getById')->willReturnCallback(function (int $id) {
            if (!isset($this->categories[$id])) {
                throw new NoSuchEntityException();
            }
            return $this->categories[$id];
        });
        $tagRepo = $this->createStub(TagRepositoryInterface::class);
        $tagRepo->method('getById')->willReturnCallback(function (int $id) {
            if (!isset($this->tags[$id])) {
                throw new NoSuchEntityException();
            }
            return $this->tags[$id];
        });
        $authorRepo = $this->createStub(AuthorRepositoryInterface::class);
        $authorRepo->method('getById')->willReturnCallback(function (int $id) {
            if ($id === 99) {
                throw new \RuntimeException('author db');
            }
            if ($id !== 2) {
                throw new NoSuchEntityException();
            }
            return $this->makeModel(Author::class, ['author_id' => 2, 'display_name' => 'Jane']);
        });

        $resource = $this->createStub(PostResource::class);
        $resource->method('getPrimaryCategoryId')->willReturnCallback(fn () => $this->resourceData['primary']);
        $resource->method('getCategoryIds')->willReturnCallback(fn () => $this->resourceData['categories']);
        $resource->method('getTagIds')->willReturnCallback(fn () => $this->resourceData['tags']);
        $resource->method('getRelatedIds')->willReturnCallback(fn () => $this->resourceData['related']);
        $resource->method('getConnection')->willReturnCallback(fn () => $this->connection ?? $this->connectionStub());
        $resource->method('getTable')->willReturnArgument(0);
        $resource->method('getMainTable')->willReturn('panth_blog_post');

        $postUrl = $this->createStub(PostUrlBuilder::class);
        $postUrl->method('getPostUrl')->willReturnCallback(static fn ($p) => 'https://s.test/blog/' . $p->getUrlKey());
        $postUrl->method('getCanonicalUrl')->willReturnCallback(
            static fn ($p) => (string) $p->getCanonicalUrl() !== '' ? (string) $p->getCanonicalUrl() : 'https://s.test/blog/' . $p->getUrlKey()
        );
        $categoryUrl = $this->createStub(CategoryUrlBuilder::class);
        $categoryUrl->method('getCategoryUrl')->willReturnCallback(static fn ($c) => 'https://s.test/blog/category/' . $c->getUrlKey());
        $tagUrl = $this->createStub(TagUrlBuilder::class);
        $tagUrl->method('getTagUrl')->willThrowException(new \RuntimeException('tag url'));
        $authorUrl = $this->createStub(AuthorUrlBuilder::class);
        $authorUrl->method('getAuthorUrl')->willReturn('https://s.test/blog/author/jane');

        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturnCallback(static fn ($t = 'link') => $t === 'media' ? 'https://s.test/media/' : 'https://s.test/');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $visibility = $this->createStub(StoreVisibility::class);
        $visibility->method('isCategoryVisible')->willReturnCallback(fn (int $id) => !in_array($id, $this->invisibleCategories, true));
        $visibility->method('isPostVisible')->willReturnCallback(fn (int $id) => !in_array($id, $this->invisiblePosts, true));
        $visibility->method('filterPosts')->willReturnArgument(0);

        return new PostView(
            $registry,
            $config,
            $postRepo,
            $categoryRepo,
            $tagRepo,
            $authorRepo,
            $resource,
            $postUrl,
            $categoryUrl,
            $tagUrl,
            $authorUrl,
            $this->jsonLd ?? $this->createStub(JsonLdRenderer::class),
            new ReadingTimeCalculator(),
            $storeManager,
            $this->logger ?? $this->createStub(LoggerInterface::class),
            $visibility
        );
    }

    private function post(array $data = []): Post
    {
        return $this->makeModel(Post::class, $data + ['post_id' => 1, 'title' => 'Hello', 'url_key' => 'hello', 'status' => 'published']);
    }

    private function category(int $id, int $active = 1, string $name = 'News'): Category
    {
        return $this->categories[$id] = $this->makeModel(Category::class, ['category_id' => $id, 'is_active' => $active, 'name' => $name, 'url_key' => strtolower($name)]);
    }

    public function testEverythingIsEmptyWithoutPost(): void
    {
        $view = $this->view(null);
        $this->assertNull($view->getPost());
        $this->assertNull($view->getAuthor());
        $this->assertNull($view->getPrimaryCategory());
        $this->assertSame([], $view->getCategories());
        $this->assertSame([], $view->getTags());
        $this->assertSame([], $view->getRelatedPosts());
        $this->assertSame('', $view->getCanonicalUrl());
        $this->assertSame('', $view->getDocumentTitle());
        $this->assertSame('', $view->getTldr());
        $this->assertSame('Default', $view->getMetaDescription());
        $this->assertNull($view->getOgImage());
        $this->assertSame(1, $view->getReadingTime());
        $this->assertSame(0, $view->getWordCount());
        $this->assertSame('', $view->getJsonLd());
        $this->assertSame([], $view->getCitations());
        $this->assertFalse($view->isTocEnabled());
        $this->assertSame([], $view->extractToc());
        $this->assertSame('', $view->getProcessedContent());
        $this->assertSame([], $view->getShareLinks());
    }

    public function testAuthorResolution(): void
    {
        $this->assertSame('Jane', $this->view($this->post(['author_id' => 2]))->getAuthor()->getDisplayName());
        $this->assertNull($this->view($this->post(['author_id' => 5]))->getAuthor());
        $this->assertNull($this->view($this->post())->getAuthor());

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger->expects($this->once())->method('warning')->with($this->stringContains('author db'));
        $view = $this->view($this->post(['author_id' => 99]));
        $this->assertNull($view->getAuthor());
        $this->assertNull($view->getAuthor());
    }

    public function testCategoriesFilterInactiveAndInvisible(): void
    {
        $this->category(1, 1, 'News');
        $this->category(2, 0, 'Hidden');
        $this->category(3, 1, 'Elsewhere');
        $this->invisibleCategories = [3];
        $this->resourceData['categories'] = [1, 2, 3, 4];
        $this->resourceData['primary'] = 3;

        $view = $this->view($this->post());
        $this->assertSame(['News'], array_map(static fn ($c) => $c->getName(), $view->getCategories()));
        $this->assertNull($view->getPrimaryCategory());
    }

    public function testPrimaryCategoryAndTags(): void
    {
        $this->category(1);
        $this->resourceData['primary'] = 1;
        $this->tags[7] = $this->makeModel(Tag::class, ['name' => 'php']);
        $this->resourceData['tags'] = [7, 8];

        $view = $this->view($this->post());
        $this->assertSame('News', $view->getPrimaryCategory()->getName());
        $this->assertSame(['php'], array_map(static fn ($t) => $t->getName(), $view->getTags()));
        $this->assertSame('', $view->getTagUrl($this->tags[7]));
    }

    public function testRelatedPostsManualThenAuto(): void
    {
        $this->cfg['getRelatedPostsCount'] = 3;
        $this->posts = [
            5 => $this->post(['post_id' => 5, 'url_key' => 'five']),
            6 => $this->post(['post_id' => 6, 'status' => 'draft']),
            7 => $this->post(['post_id' => 7]),
            8 => $this->post(['post_id' => 8, 'url_key' => 'eight']),
            9 => $this->post(['post_id' => 9, 'url_key' => 'nine']),
        ];
        $this->invisiblePosts = [7];
        $this->resourceData['related'] = [1, 5, 6, 7, 404];
        $this->category(2);
        $this->resourceData['primary'] = 2;
        $this->connection = $this->connectionStub();
        $this->connection->method('fetchCol')->willReturn(['8', '9', '10']);

        $related = $this->view($this->post())->getRelatedPosts();
        $this->assertSame(['five', 'eight', 'nine'], array_map(static fn ($p) => $p->getUrlKey(), $related));
    }

    public function testRelatedPostsDisabledByCount(): void
    {
        $this->resourceData['related'] = [5];
        $this->posts = [5 => $this->post(['post_id' => 5])];
        $this->assertSame([], $this->view($this->post())->getRelatedPosts());
    }

    public function testRelatedPostsWrappersAndToc(): void
    {
        $this->cfg['getRelatedPostsCount'] = 1;
        $this->posts = [5 => $this->post(['post_id' => 5])];
        $this->resourceData['related'] = [5];
        $view = $this->view($this->post(['enable_toc' => 1, 'content' => '<h2>One</h2><h2>Two</h2>']));

        $related = new RelatedPosts($view);
        $this->assertTrue($related->hasItems());
        $this->assertCount(1, $related->getItems());

        $toc = new Toc($view);
        $this->assertTrue($toc->isEnabled());
        $this->assertSame(['one', 'two'], array_column($toc->getItems(), 'anchor'));
    }

    public function testCanonicalAndTitle(): void
    {
        $this->assertSame('https://s.test/blog/hello', $this->view($this->post())->getCanonicalUrl());
        $this->assertSame('https://x.test/c', $this->view($this->post(['canonical_url' => 'https://x.test/c']))->getCanonicalUrl());
        $this->assertSame('Meta', $this->view($this->post(['meta_title' => ' Meta ']))->getDocumentTitle());

        $this->cfg['getTitleTemplate'] = '{{post.title}} - {{post.category}} - {{post.author}}';
        $this->category(1);
        $this->resourceData['primary'] = 1;
        $this->assertSame('Hello - News - Jane', $this->view($this->post(['author_id' => 2]))->getDocumentTitle());
    }

    public function testMetaDescriptionFallbacks(): void
    {
        $this->assertSame('Meta', $this->view($this->post(['meta_description' => ' Meta ']))->getMetaDescription());
        $long = str_repeat("\u{e9}", 200);
        $this->assertSame(155, mb_strlen($this->view($this->post(['short_description' => '<p>' . $long . '</p>']))->getMetaDescription()));
        $this->assertSame('Default', $this->view($this->post())->getMetaDescription());
    }

    public function testOgImageAndTldr(): void
    {
        $this->assertSame('https://s.test/media/blog/og.png', $this->view($this->post(['og_image' => '/og.png', 'featured_image' => 'f.png']))->getOgImage());
        $this->assertSame('https://s.test/media/blog/f.png', $this->view($this->post(['featured_image' => 'f.png']))->getOgImage());
        $this->assertSame('Short', $this->view($this->post(['tldr_summary' => ' Short ']))->getTldr());
    }

    public function testReadingTimeUsesStoredOrComputedValues(): void
    {
        $stored = $this->view($this->post(['reading_time_min' => 7, 'word_count' => 1400]));
        $this->assertSame(7, $stored->getReadingTime());
        $this->assertSame(1400, $stored->getWordCount());

        $computed = $this->view($this->post(['content' => str_repeat('word ', 250)]));
        $this->assertSame(3, $computed->getReadingTime());
        $this->assertSame(250, $computed->getWordCount());

        $wordsFirst = $this->view($this->post(['content' => str_repeat('word ', 250)]));
        $this->assertSame(250, $wordsFirst->getWordCount());
        $this->assertSame(3, $wordsFirst->getReadingTime());
    }

    public function testJsonLdPassesBreadcrumbs(): void
    {
        $this->category(1);
        $this->resourceData['primary'] = 1;
        $this->jsonLd = $this->createMock(JsonLdRenderer::class);
        $this->jsonLd->expects($this->once())->method('renderForPost')
            ->willReturnCallback(function ($post, $author, $primary, $tags, $crumbs) {
                $this->assertSame([
                    ['name' => 'Home', 'item' => 'https://s.test/'],
                    ['name' => 'Blog', 'item' => 'https://s.test/blog'],
                    ['name' => 'News', 'item' => 'https://s.test/blog/category/news'],
                    ['name' => 'Hello', 'item' => 'https://s.test/blog/hello'],
                ], $crumbs);
                return '{"ok":true}';
            });
        $this->assertSame('{"ok":true}', $this->view($this->post())->getJsonLd());
    }

    public function testJsonLdFailureReturnsEmpty(): void
    {
        $this->jsonLd = $this->createStub(JsonLdRenderer::class);
        $this->jsonLd->method('renderForPost')->willThrowException(new \RuntimeException('x'));
        $this->assertSame('', $this->view($this->post())->getJsonLd());
    }

    public function testCitations(): void
    {
        $json = json_encode([
            ['title' => 'Spec', 'url' => 'https://spec.test'],
            ['url' => 'https://only-url.test', 'type' => ''],
            ['label' => '', 'url' => ''],
            'junk',
            ['label' => 'Book', 'type' => 'Book'],
        ]);
        $this->assertSame([
            ['label' => 'Spec', 'url' => 'https://spec.test', 'type' => 'WebPage'],
            ['label' => 'https://only-url.test', 'url' => 'https://only-url.test', 'type' => 'WebPage'],
            ['label' => 'Book', 'url' => '', 'type' => 'Book'],
        ], $this->view($this->post(['citation_list' => $json]))->getCitations());
        $this->assertSame([], $this->view($this->post(['citation_list' => 'not json']))->getCitations());
    }

    public function testDisplayFlags(): void
    {
        $view = $this->view($this->post());
        $this->assertTrue($view->isShowReadingTime());
        $this->assertFalse($view->isShowAuthorBio());
        $this->assertTrue($view->isShowShareButtons());
    }

    public function testTocRequiresPostFlagConfigAndEnoughHeadings(): void
    {
        $content = '<h2>One</h2><h2>Two</h2>';
        $this->assertFalse($this->view($this->post(['enable_toc' => 0, 'content' => $content]))->isTocEnabled());
        $this->assertFalse($this->view($this->post(['enable_toc' => 1, 'content' => '<h2>Only</h2>']))->isTocEnabled());
        $this->cfg['isShowToc'] = false;
        $this->assertFalse($this->view($this->post(['enable_toc' => 1, 'content' => $content]))->isTocEnabled());
    }

    public function testExtractTocAndProcessedContentAgreeForPlainHeadings(): void
    {
        $content = '<h2>Intro &amp; Goals</h2><p>x</p><h3 class="s">Details</h3><h2>Intro &amp; Goals</h2><h2> </h2>';
        $view = $this->view($this->post(['content' => $content]));

        $this->assertSame([
            ['anchor' => 'intro-goals', 'text' => 'Intro &amp; Goals', 'level' => 2],
            ['anchor' => 'details', 'text' => 'Details', 'level' => 3],
            ['anchor' => 'intro-goals-2', 'text' => 'Intro &amp; Goals', 'level' => 2],
        ], $view->extractToc());

        $html = $view->getProcessedContent();
        $this->assertStringContainsString('<h2 id="intro-goals">Intro &amp; Goals</h2>', $html);
        $this->assertStringContainsString('<h3 class="s" id="details">Details</h3>', $html);
        $this->assertStringContainsString('<h2 id="intro-goals-2">', $html);
    }

    public function testExplicitHeadingIdsAreReservedInProcessedContent(): void
    {
        $view = $this->view($this->post(['content' => '<h2 id="intro">Start</h2><h2>Intro</h2>']));

        $this->assertSame(['intro', 'intro-2'], array_column($view->extractToc(), 'anchor'));
        $this->assertSame('<h2 id="intro">Start</h2><h2 id="intro-2">Intro</h2>', $view->getProcessedContent());
    }

    public function testLaterExplicitHeadingIdKeepsItsValueAndGeneratedIdAvoidsIt(): void
    {
        $view = $this->view($this->post(['content' => '<h2>Intro</h2><h2 id="intro">Start</h2>']));

        $this->assertSame(['intro-2', 'intro'], array_column($view->extractToc(), 'anchor'));
        $this->assertSame('<h2 id="intro-2">Intro</h2><h2 id="intro">Start</h2>', $view->getProcessedContent());
    }

    public function testShareLinks(): void
    {
        $links = $this->view($this->post(['title' => 'A & B']))->getShareLinks();
        $this->assertSame(['twitter', 'linkedin', 'copy'], array_column($links, 'network'));
        $this->assertSame('https://twitter.com/intent/tweet?url=https%3A%2F%2Fs.test%2Fblog%2Fhello&text=A%20%26%20B', $links[0]['url']);
        $this->assertSame('https://s.test/blog/hello', $links[2]['url']);
    }

    public function testUrlHelpers(): void
    {
        $view = $this->view($this->post());
        $this->assertSame('https://s.test/blog/other', $view->getPostUrl($this->post(['url_key' => 'other'])));
        $this->assertSame('https://s.test/blog/category/news', $view->getCategoryUrl($this->category(1)));
        $this->assertSame('https://s.test/blog/author/jane', $view->getAuthorUrl($this->makeModel(Author::class)));
        $this->assertSame('https://s.test/media/blog/a/b.png', $view->getMediaUrl('/a/b.png'));
        $this->assertSame('https://cdn.test/a.png', $view->getMediaUrl('https://cdn.test/a.png'));
        $this->assertSame('', $view->getMediaUrl(''));
    }

    public function testScriptUrlsInContentAreNeutralised(): void
    {
        $content = '<p><a href="javascript:alert(1)">a</a><a href=\'vbscript:x\'>b</a>'
            . '<iframe src=" data:text/html;base64,AAA"></iframe><a href="https://ok.test/">c</a></p>';
        $html = $this->view($this->post(['content' => $content]))->getProcessedContent();
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('vbscript:', $html);
        $this->assertStringNotContainsString('data:text/html', $html);
        $this->assertStringContainsString('href="#"', $html);
        $this->assertStringContainsString('href="https://ok.test/"', $html);
    }
}
