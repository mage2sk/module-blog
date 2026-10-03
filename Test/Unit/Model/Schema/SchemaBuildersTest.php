<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model\Schema;

use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Author;
use Panth\Blog\Model\Category;
use Panth\Blog\Model\Post;
use Panth\Blog\Model\Schema\BlogPostingBuilder;
use Panth\Blog\Model\Schema\BreadcrumbBuilder;
use Panth\Blog\Model\Schema\CollectionPageBuilder;
use Panth\Blog\Model\Schema\FaqPageBuilder;
use Panth\Blog\Model\Schema\GraphAssembler;
use Panth\Blog\Model\Schema\HowToBuilder;
use Panth\Blog\Model\Schema\JsonLdRenderer;
use Panth\Blog\Model\Schema\PersonBuilder;
use Panth\Blog\Model\Tag;
use Panth\Blog\Model\Url\AuthorUrlBuilder;
use Panth\Blog\Model\Url\CategoryUrlBuilder;
use Panth\Blog\Model\Url\PostUrlBuilder;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\TestCase;

class SchemaBuildersTest extends TestCase
{
    use BlogTestHelpers;

    private function authorUrl(): AuthorUrlBuilder
    {
        $url = $this->createStub(AuthorUrlBuilder::class);
        $url->method('getAuthorUrl')->willReturn('https://s.test/blog/author/jane');
        return $url;
    }

    private function postingBuilder(): BlogPostingBuilder
    {
        $postUrl = $this->createStub(PostUrlBuilder::class);
        $postUrl->method('getPostUrl')->willReturn('https://s.test/blog/post');
        return new BlogPostingBuilder(
            $postUrl,
            $this->authorUrl(),
            $this->createStub(CategoryUrlBuilder::class),
            new PersonBuilder($this->authorUrl()),
            $this->storeManager()
        );
    }

    private function storeManager(): StoreManagerInterface
    {
        $store = $this->createStub(\Magento\Store\Model\Store::class);
        $store->method('getBaseUrl')->willReturn('https://s.test/media/');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        return $storeManager;
    }

    private function author(): Author
    {
        return $this->makeModel(Author::class, [
            'display_name' => 'Jane',
            'role' => 'Editor',
            'same_as' => '["https://x.com/jane"]',
            'knows_about' => 'not json',
            'alumni_of' => 'MIT',
        ]);
    }

    public function testBlogPostingWithFullData(): void
    {
        $post = $this->makeModel(Post::class, [
            'title' => 'Hello',
            'short_description' => 'Summary',
            'featured_image' => 'a.jpg',
            'og_image' => 'b.jpg',
            'published_at' => '2026-01-02T03:04:05+00:00',
            'updated_at' => '',
            'word_count' => '900',
            'reading_time_min' => '4',
        ]);
        $tags = [$this->makeModel(Tag::class, ['name' => 'php']), $this->makeModel(Tag::class, ['name' => 'magento'])];
        $category = $this->makeModel(Category::class, ['name' => 'News']);

        $data = $this->postingBuilder()->build($post, $this->author(), $category, $tags);

        $this->assertSame('BlogPosting', $data['@type']);
        $this->assertSame('https://s.test/blog/post#article', $data['@id']);
        $this->assertSame('Summary', $data['description']);
        $this->assertSame(['https://s.test/media/blog/a.jpg', 'https://s.test/media/blog/b.jpg'], $data['image']);
        $this->assertSame('2026-01-02T03:04:05+00:00', $data['datePublished']);
        $this->assertSame('', $data['dateModified']);
        $this->assertSame(900, $data['wordCount']);
        $this->assertSame('PT4M', $data['timeRequired']);
        $this->assertSame('News', $data['articleSection']);
        $this->assertSame('php, magento', $data['keywords']);
        $this->assertSame(['@id' => 'https://s.test/blog/author/jane#person'], $data['author']);
        $this->assertSame(['@type' => 'WebPage', '@id' => 'https://s.test/blog/post'], $data['mainEntityOfPage']);
    }

    public function testBlogPostingFallbacks(): void
    {
        $post = $this->makeModel(Post::class, [
            'title' => 'Hello',
            'content' => '<p>' . str_repeat('a', 300) . '</p>',
            'published_at' => 'garbage date',
        ]);

        $data = $this->postingBuilder()->build($post);
        $this->assertSame(str_repeat('a', 160), $data['description']);
        $this->assertNull($data['image']);
        $this->assertNull($data['author']);
        $this->assertNull($data['articleSection']);
        $this->assertSame('', $data['keywords']);
        $this->assertSame('', $data['datePublished']);
        $this->assertSame('PT1M', $data['timeRequired']);
    }

    public function testDescriptionFallbackCanSplitMultibyteCharacters(): void
    {
        $post = $this->makeModel(Post::class, ['content' => 'a' . str_repeat("\u{e9}", 200)]);
        $data = $this->postingBuilder()->build($post);

        $this->assertSame(160, strlen($data['description']));
        $this->assertFalse(mb_check_encoding($data['description'], 'UTF-8'));
        $this->assertSame('', (new GraphAssembler())->assemble([$data]));
    }

    public function testPersonBuilder(): void
    {
        $person = (new PersonBuilder($this->authorUrl()))->build($this->author());
        $this->assertSame('https://s.test/blog/author/jane#person', $person['@id']);
        $this->assertSame('Editor', $person['jobTitle']);
        $this->assertSame(['https://x.com/jane'], $person['sameAs']);
        $this->assertSame([], $person['knowsAbout']);
        $this->assertSame(['@type' => 'CollegeOrUniversity', 'name' => 'MIT'], $person['alumniOf']);

        $plain = (new PersonBuilder($this->authorUrl()))->build($this->makeModel(Author::class, ['display_name' => 'X']));
        $this->assertNull($plain['alumniOf']);
    }

    public function testBreadcrumbBuilder(): void
    {
        $data = (new BreadcrumbBuilder())->build([
            ['name' => 'Home', 'item' => '/'],
            ['name' => 'Blog'],
        ]);
        $this->assertSame([
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => ''],
        ], $data['itemListElement']);
    }

    public function testCollectionPageBuilder(): void
    {
        $builder = new CollectionPageBuilder();
        $index = $builder->buildBlogIndex('Blog', '/blog', 'desc', []);
        $this->assertSame('Blog', $index['@type']);
        $this->assertNull($index['blogPost']);

        $category = $this->makeModel(Category::class, ['name' => 'News', 'description' => 'D']);
        $page = $builder->buildCategoryPage($category, '/blog/category/news', [['x' => 1]], 12);
        $this->assertSame('CollectionPage', $page['@type']);
        $this->assertSame(12, $page['mainEntity']['numberOfItems']);
        $this->assertSame([['x' => 1]], $page['mainEntity']['itemListElement']);
    }

    public function testFaqPageBuilder(): void
    {
        $builder = new FaqPageBuilder();
        $this->assertNull($builder->build([]));
        $this->assertNull($builder->build([['question' => 'Q'], ['answer' => 'A']]));

        $data = $builder->build([['question' => 'Q?', 'answer' => 'A.'], ['question' => '', 'answer' => 'x']]);
        $this->assertSame('FAQPage', $data['@type']);
        $this->assertCount(1, $data['mainEntity']);
        $this->assertSame('A.', $data['mainEntity'][0]['acceptedAnswer']['text']);
    }

    public function testHowToBuilder(): void
    {
        $builder = new HowToBuilder();
        $this->assertNull($builder->extractFromContent(''));
        $this->assertNull($builder->extractFromContent('<h2>Step 1. Only one</h2>'));
        $this->assertNull($builder->extractFromContent('<h2>Intro</h2><h2>Outro</h2>'));

        $data = $builder->extractFromContent('<h2>Step 1. Prepare <em>tools</em></h2><p>x</p><h2 id="b">2 Assemble</h2>', 'Build it');
        $this->assertSame('HowTo', $data['@type']);
        $this->assertSame('Build it', $data['name']);
        $this->assertSame(['Prepare tools', 'Assemble'], array_column($data['step'], 'name'));
    }

    public function testGraphAssemblerStripsNullsAndEscapesHtml(): void
    {
        $json = (new GraphAssembler())->assemble([
            null,
            ['@type' => 'Thing', 'name' => '</script><b>&', 'empty' => [], 'nested' => ['a' => null, 'b' => 1], 'list' => [null, 'x']],
            ['only' => null],
        ]);

        $this->assertStringNotContainsString('</script>', $json);
        $decoded = json_decode($json, true);
        $this->assertSame('https://schema.org', $decoded['@context']);
        $this->assertCount(1, $decoded['@graph']);
        $this->assertSame(['@type' => 'Thing', 'name' => '</script><b>&', 'nested' => ['b' => 1], 'list' => ['x']], $decoded['@graph'][0]);
    }

    private function renderer(bool $howTo): JsonLdRenderer
    {
        $config = $this->createStub(Config::class);
        $config->method('isHowtoAutoDetect')->willReturn($howTo);
        return new JsonLdRenderer(
            $this->postingBuilder(),
            new PersonBuilder($this->authorUrl()),
            new BreadcrumbBuilder(),
            new FaqPageBuilder(),
            new HowToBuilder(),
            new CollectionPageBuilder(),
            new GraphAssembler(),
            $config
        );
    }

    private function types(string $json): array
    {
        return array_column(json_decode($json, true)['@graph'], '@type');
    }

    public function testRenderForPostIncludesOptionalEntities(): void
    {
        $post = $this->makeModel(Post::class, [
            'title' => 'Guide',
            'content' => '<h2>1. One</h2><h2>2. Two</h2>',
        ]);
        $json = $this->renderer(true)->renderForPost(
            $post,
            $this->author(),
            null,
            [],
            [['name' => 'Home', 'item' => '/']],
            [['question' => 'Q', 'answer' => 'A']]
        );
        $this->assertSame(['BlogPosting', 'Person', 'BreadcrumbList', 'FAQPage', 'HowTo'], $this->types($json));

        $minimal = $this->renderer(false)->renderForPost($post, null, null, [], []);
        $this->assertSame(['BlogPosting'], $this->types($minimal));
    }

    public function testRenderForListingPages(): void
    {
        $renderer = $this->renderer(false);
        $category = $this->makeModel(Category::class, ['name' => 'News']);
        $crumbs = [['name' => 'Home', 'item' => '/']];

        $this->assertSame(['CollectionPage', 'BreadcrumbList'], $this->types($renderer->renderForCategory($category, '/c', [], $crumbs, 0)));
        $this->assertSame(['Blog'], $this->types($renderer->renderForBlogIndex('Blog', '/blog', 'd', [])));

        $tag = json_decode($renderer->renderForTag('PHP', '/t', 'd', [['p' => 1]], 3, $crumbs), true)['@graph'];
        $this->assertSame('CollectionPage', $tag[0]['@type']);
        $this->assertSame(3, $tag[0]['mainEntity']['numberOfItems']);

        $author = json_decode($renderer->renderForAuthor($this->author()), true)['@graph'];
        $this->assertSame(['ProfilePage', 'Person'], array_column($author, '@type'));
        $this->assertSame('Jane', $author[0]['mainEntity']['name']);
    }
}
