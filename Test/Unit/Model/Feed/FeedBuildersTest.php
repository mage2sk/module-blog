<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model\Feed;

use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Author;
use Panth\Blog\Model\Category;
use Panth\Blog\Model\Feed\AtomBuilder;
use Panth\Blog\Model\Feed\RssBuilder;
use Panth\Blog\Model\Post;
use Panth\Blog\Model\Tag;
use Panth\Blog\Model\Text\Truncator;
use Panth\Blog\Model\Url\AuthorUrlBuilder;
use Panth\Blog\Model\Url\CategoryUrlBuilder;
use Panth\Blog\Model\Url\FeedUrlBuilder;
use Panth\Blog\Model\Url\PostUrlBuilder;
use Panth\Blog\Model\Url\TagUrlBuilder;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\TestCase;

class FeedBuildersTest extends TestCase
{
    use BlogTestHelpers;

    private string $timezone;

    protected function setUp(): void
    {
        $this->timezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timezone);
    }

    private function args(bool $fullContent = false, bool $storeFails = false): array
    {
        $postUrl = $this->createStub(PostUrlBuilder::class);
        $postUrl->method('getPostUrl')->willReturnCallback(static fn ($p) => 'https://s.test/blog/' . $p->getUrlKey());
        $categoryUrl = $this->createStub(CategoryUrlBuilder::class);
        $categoryUrl->method('getCategoryUrl')->willReturn('https://s.test/blog/category/news');
        $tagUrl = $this->createStub(TagUrlBuilder::class);
        $tagUrl->method('getTagUrl')->willReturn('https://s.test/blog/tag/php');
        $authorUrl = $this->createStub(AuthorUrlBuilder::class);
        $authorUrl->method('getAuthorUrl')->willReturn('https://s.test/blog/author/jane');
        $feedUrl = $this->createStub(FeedUrlBuilder::class);
        $feedUrl->method('getMainRssUrl')->willReturn('https://s.test/blog/feed.xml');
        $feedUrl->method('getMainAtomUrl')->willReturn('https://s.test/blog/feed/atom.xml');
        $feedUrl->method('getCategoryRssUrl')->willReturn('https://s.test/blog/feed/category/news.xml');
        $feedUrl->method('getTagRssUrl')->willReturn('https://s.test/blog/feed/tag/php.xml');
        $feedUrl->method('getAuthorRssUrl')->willReturn('https://s.test/blog/feed/author/jane.xml');

        $storeManager = $this->createStub(StoreManagerInterface::class);
        if ($storeFails) {
            $storeManager->method('getStore')->willThrowException(new \RuntimeException('x'));
        } else {
            $store = $this->createStub(Store::class);
            $store->method('getBaseUrl')->willReturnCallback(static fn ($type = 'link') => $type === 'media' ? 'https://s.test/media/' : 'https://s.test/');
            $storeManager->method('getStore')->willReturn($store);
        }

        $config = $this->createStub(Config::class);
        $config->method('getIndexTitle')->willReturn('');
        $config->method('getIndexDescription')->willReturn('All the news');
        $config->method('getRouteFrontName')->willReturn('blog');
        $config->method('isFeedFullContent')->willReturn($fullContent);

        return [$postUrl, $categoryUrl, $tagUrl, $authorUrl, $feedUrl, $storeManager, $config, new Truncator()];
    }

    private function posts(): array
    {
        return [
            $this->makeModel(Post::class, [
                'title' => 'Tips & Tricks',
                'url_key' => 'tips',
                'short_description' => '  Short summary ',
                'content' => '<p>Full <b>body</b></p>',
                'published_at' => '2026-02-03 04:05:06',
                'author_id' => 1,
                'featured_image' => '/2026/hero.jpg',
                'featured_image_alt' => 'Hero <alt>',
            ]),
            $this->makeModel(Post::class, [
                'title' => 'Second',
                'url_key' => 'second',
                'content' => '<p>' . str_repeat('word ', 100) . '</p>',
                'created_at' => '2025-12-01 00:00:00',
            ]),
        ];
    }

    private function resolver(): callable
    {
        $author = $this->makeModel(Author::class, ['display_name' => 'Jane']);
        return static fn ($post) => $post->getAuthorId() === 1 ? $author : null;
    }

    private function xpath(string $xml, array $ns = []): \DOMXPath
    {
        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml), 'feed must be well-formed XML');
        $xp = new \DOMXPath($doc);
        foreach ($ns as $prefix => $uri) {
            $xp->registerNamespace($prefix, $uri);
        }
        return $xp;
    }

    public function testSiteWideRss(): void
    {
        $xml = (new RssBuilder(...$this->args()))->buildSiteWideRss(1, $this->posts(), $this->resolver());
        $xp = $this->xpath($xml, ['dc' => 'http://purl.org/dc/elements/1.1/', 'media' => 'http://search.yahoo.com/mrss/', 'atom' => 'http://www.w3.org/2005/Atom']);

        $this->assertSame('Blog', $xp->evaluate('string(/rss/channel/title)'));
        $this->assertSame('https://s.test/blog', $xp->evaluate('string(/rss/channel/link)'));
        $this->assertSame('All the news', $xp->evaluate('string(/rss/channel/description)'));
        $this->assertSame('https://s.test/blog/feed.xml', $xp->evaluate('string(/rss/channel/atom:link/@href)'));
        $this->assertSame(2.0, $xp->evaluate('count(/rss/channel/item)'));

        $this->assertSame('Tips & Tricks', $xp->evaluate('string(/rss/channel/item[1]/title)'));
        $this->assertSame('https://s.test/blog/tips', $xp->evaluate('string(/rss/channel/item[1]/guid)'));
        $this->assertSame('Short summary', $xp->evaluate('string(/rss/channel/item[1]/description)'));
        $this->assertSame('Tue, 03 Feb 2026 04:05:06 +0000', $xp->evaluate('string(/rss/channel/item[1]/pubDate)'));
        $this->assertSame('Jane', $xp->evaluate('string(/rss/channel/item[1]/dc:creator)'));
        $this->assertSame('https://s.test/media/blog/2026/hero.jpg', $xp->evaluate('string(/rss/channel/item[1]/media:content/@url)'));
        $this->assertSame('Hero <alt>', $xp->evaluate('string(/rss/channel/item[1]/media:content/media:title)'));
        $this->assertSame(0.0, $xp->evaluate('count(//*[local-name()="encoded"])'));

        $summary = $xp->evaluate('string(/rss/channel/item[2]/description)');
        $this->assertSame(320, strlen($summary));
        $this->assertStringEndsWith('...', $summary);
        $this->assertSame('Mon, 01 Dec 2025 00:00:00 +0000', $xp->evaluate('string(/rss/channel/item[2]/pubDate)'));
        $this->assertSame(0.0, $xp->evaluate('count(/rss/channel/item[2]/dc:creator)'));
    }

    public function testRssFullContentAndStoreFallback(): void
    {
        $xml = (new RssBuilder(...$this->args(true, true)))->buildSiteWideRss(1, $this->posts(), $this->resolver());
        $xp = $this->xpath($xml, ['content' => 'http://purl.org/rss/1.0/modules/content/', 'media' => 'http://search.yahoo.com/mrss/']);

        $this->assertSame('<p>Full <b>body</b></p>', $xp->evaluate('string(/rss/channel/item[1]/content:encoded)'));
        $this->assertSame('/blog', $xp->evaluate('string(/rss/channel/link)'));
        $this->assertSame('/2026/hero.jpg', $xp->evaluate('string(/rss/channel/item[1]/media:content/@url)'));
    }

    public function testRssTaxonomyChannels(): void
    {
        $builder = new RssBuilder(...$this->args());
        $category = $this->makeModel(Category::class, ['name' => 'News', 'meta_description' => 'Meta desc']);
        $tag = $this->makeModel(Tag::class, ['name' => 'PHP', 'description' => 'Tag desc']);
        $author = $this->makeModel(Author::class, ['display_name' => 'Jane', 'short_bio' => 'Bio']);

        $xp = $this->xpath($builder->buildCategoryRss(1, [], $category, $this->resolver()));
        $this->assertSame('News', $xp->evaluate('string(/rss/channel/title)'));
        $this->assertSame('Meta desc', $xp->evaluate('string(/rss/channel/description)'));
        $this->assertSame('https://s.test/blog/category/news', $xp->evaluate('string(/rss/channel/link)'));

        $xp = $this->xpath($builder->buildTagRss(1, [], $tag, $this->resolver()));
        $this->assertSame('PHP', $xp->evaluate('string(/rss/channel/title)'));
        $this->assertSame('Tag desc', $xp->evaluate('string(/rss/channel/description)'));

        $xp = $this->xpath($builder->buildAuthorRss(1, [], $author, $this->resolver()));
        $this->assertSame('Jane', $xp->evaluate('string(/rss/channel/title)'));
        $this->assertSame('Bio', $xp->evaluate('string(/rss/channel/description)'));
    }

    public function testRfc822HandlesInvalidDates(): void
    {
        $builder = new RssBuilder(...$this->args());
        $this->assertSame('Thu, 01 Jan 2026 00:00:00 +0000', $builder->rfc822('2026-01-01 00:00:00 UTC'));
        $this->assertMatchesRegularExpression('/^\w{3}, \d{2} \w{3} \d{4} \d{2}:\d{2}:\d{2} \+0000$/', $builder->rfc822('not a date'));
    }

    public function testSiteWideAtom(): void
    {
        $xml = (new AtomBuilder(...$this->args(true)))->buildSiteWideAtom(1, $this->posts(), $this->resolver());
        $xp = $this->xpath($xml, ['a' => 'http://www.w3.org/2005/Atom']);

        $this->assertSame('https://s.test/blog', $xp->evaluate('string(/a:feed/a:id)'));
        $this->assertSame('Blog', $xp->evaluate('string(/a:feed/a:title)'));
        $this->assertSame('All the news', $xp->evaluate('string(/a:feed/a:subtitle)'));
        $this->assertSame('https://s.test/blog/feed/atom.xml', $xp->evaluate('string(/a:feed/a:link[@rel="self"]/@href)'));
        $this->assertSame(2.0, $xp->evaluate('count(/a:feed/a:entry)'));
        $this->assertSame('Tips & Tricks', $xp->evaluate('string(/a:feed/a:entry[1]/a:title)'));
        $this->assertSame('2026-02-03T04:05:06Z', $xp->evaluate('string(/a:feed/a:entry[1]/a:published)'));
        $this->assertSame('2026-02-03T04:05:06Z', $xp->evaluate('string(/a:feed/a:entry[1]/a:updated)'));
        $this->assertSame('Jane', $xp->evaluate('string(/a:feed/a:entry[1]/a:author/a:name)'));
        $this->assertSame('Short summary', $xp->evaluate('string(/a:feed/a:entry[1]/a:summary)'));
        $this->assertSame('<p>Full <b>body</b></p>', $xp->evaluate('string(/a:feed/a:entry[1]/a:content)'));
        $this->assertSame(0.0, $xp->evaluate('count(/a:feed/a:entry[2]/a:author)'));
    }

    public function testAtomCategoryFeedSelfLinkPointsAtRssUrl(): void
    {
        $category = $this->makeModel(Category::class, ['name' => 'News']);
        $xml = (new AtomBuilder(...$this->args()))->buildCategoryAtom(1, [], $category, $this->resolver());
        $xp = $this->xpath($xml, ['a' => 'http://www.w3.org/2005/Atom']);

        $this->assertSame('https://s.test/blog/category/news', $xp->evaluate('string(/a:feed/a:id)'));
        $this->assertSame(0.0, $xp->evaluate('count(/a:feed/a:subtitle)'));
        $this->assertSame('https://s.test/blog/feed/category/news.xml', $xp->evaluate('string(/a:feed/a:link[@rel="self"]/@href)'));
    }

    public function testAtomTagAndAuthorFeeds(): void
    {
        $builder = new AtomBuilder(...$this->args());
        $tag = $this->makeModel(Tag::class, ['name' => 'PHP']);
        $author = $this->makeModel(Author::class, ['display_name' => 'Jane', 'short_bio' => 'Bio']);

        $xp = $this->xpath($builder->buildTagAtom(1, [], $tag, $this->resolver()), ['a' => 'http://www.w3.org/2005/Atom']);
        $this->assertSame('https://s.test/blog/tag/php', $xp->evaluate('string(/a:feed/a:id)'));

        $xp = $this->xpath($builder->buildAuthorAtom(1, [], $author, $this->resolver()), ['a' => 'http://www.w3.org/2005/Atom']);
        $this->assertSame('Bio', $xp->evaluate('string(/a:feed/a:subtitle)'));
        $this->assertSame('https://s.test/blog/author/jane', $xp->evaluate('string(/a:feed/a:link[@rel="alternate"]/@href)'));
    }

    public function testIso8601(): void
    {
        $builder = new AtomBuilder(...$this->args());
        $this->assertSame('2026-01-01T12:00:00Z', $builder->iso8601('2026-01-01 12:00:00 UTC'));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $builder->iso8601(''));
    }
}
