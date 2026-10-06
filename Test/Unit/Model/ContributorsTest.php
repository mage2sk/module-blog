<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\UrlInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Hreflang\BlogContributor as HreflangContributor;
use Panth\Blog\Model\HtmlSitemap\BlogContributor as HtmlSitemapContributor;
use Panth\Blog\Model\LlmsTxt\BlogContributor as LlmsTxtContributor;
use Panth\Blog\Model\Sitemap\BlogContributor as SitemapContributor;
use Panth\Blog\Model\StoreVisibility;
use Panth\Blog\Model\Url\AuthorUrlBuilder;
use Panth\Blog\Model\Url\PostUrlBuilder;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\TestCase;

class ContributorsTest extends TestCase
{
    use BlogTestHelpers;

    private function resource(AdapterInterface $connection): ResourceConnection
    {
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        return $resource;
    }

    private function url(): UrlInterface
    {
        $url = $this->createStub(UrlInterface::class);
        $url->method('getBaseUrl')->willReturn('https://s.test/');
        return $url;
    }

    private function config(): Config
    {
        $config = $this->createStub(Config::class);
        $config->method('getRouteFrontName')->willReturn('blog');
        $config->method('getTagThinThreshold')->willReturn(3);
        $config->method('isLlmsTxtIncludeEnabled')->willReturn(true);
        $config->method('getLlmsTxtMaxPosts')->willReturn(0);
        return $config;
    }

    private function connection(array $rowsByTable, array $existing): AdapterInterface
    {
        $current = null;
        $select = $this->createStub(\Magento\Framework\DB\Select::class);
        $select->method('from')->willReturnCallback(function ($table) use (&$current, $select) {
            $current = is_array($table) ? reset($table) : $table;
            return $select;
        });
        foreach (['where', 'order', 'limit'] as $m) {
            $select->method($m)->willReturnSelf();
        }
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('isTableExists')->willReturnCallback(static fn ($t) => in_array($t, $existing, true));
        $connection->method('fetchAll')->willReturnCallback(function () use (&$current, $rowsByTable) {
            return $rowsByTable[$current] ?? [];
        });
        return $connection;
    }

    public function testSitemapUrls(): void
    {
        $tables = ['panth_blog_post', 'panth_blog_category', 'panth_blog_tag', 'panth_blog_author'];
        $connection = $this->connection([
            'panth_blog_post' => [
                ['url_key' => 'hello', 'updated_at' => '', 'published_at' => '2026-01-01 00:00:00'],
                ['url_key' => '', 'updated_at' => '2026-01-01 00:00:00', 'published_at' => ''],
            ],
            'panth_blog_category' => [['url_key' => 'news', 'updated_at' => '2026-02-01 10:00:00']],
            'panth_blog_tag' => [['url_key' => 'php', 'updated_at' => 'bogus']],
            'panth_blog_author' => [['url_key' => 'jane', 'updated_at' => '2026-03-01 00:00:00']],
        ], $tables);

        $contributor = new SitemapContributor($this->resource($connection), $this->url(), $this->config());
        $this->assertSame('sitemap-blog-1.xml', $contributor->getSitemapName());

        $urls = $contributor->getUrls(1);
        $this->assertSame([
            'https://s.test/blog/hello',
            'https://s.test/blog/category/news',
            'https://s.test/blog/tag/php',
            'https://s.test/blog/author/jane',
        ], array_column($urls, 'loc'));
        $this->assertSame([0.7, 0.5, 0.4, 0.4], array_column($urls, 'priority'));
        $this->assertSame('weekly', $urls[0]['changefreq']);
        $this->assertMatchesRegularExpression('/^2026-01-01T\d{2}:00:00\+00:00$/', $urls[0]['lastmod']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $urls[2]['lastmod']);
    }

    public function testSitemapSkipsMissingTables(): void
    {
        $connection = $this->connection(['panth_blog_post' => [['url_key' => 'x']]], []);
        $this->assertSame([], (new SitemapContributor($this->resource($connection), $this->url(), $this->config()))->getUrls(1));
    }

    public function testHtmlSitemapSection(): void
    {
        $tables = ['panth_blog_post', 'panth_blog_category', 'panth_blog_author'];
        $connection = $this->connection([
            'panth_blog_post' => [['url_key' => 'hello', 'title' => 'Hello'], ['url_key' => 'untitled', 'title' => '']],
            'panth_blog_category' => [],
            'panth_blog_author' => [['url_key' => 'jane', 'display_name' => 'Jane']],
        ], $tables);

        $section = (new HtmlSitemapContributor($this->resource($connection), $this->url(), $this->config()))->getSection(1);
        $this->assertSame('Blog', $section['title']);
        $this->assertSame(70, $section['priority']);
        $this->assertSame(['Posts', 'Authors'], array_column($section['groups'], 'title'));
        $this->assertSame([['url' => 'https://s.test/blog/hello', 'label' => 'Hello']], $section['groups'][0]['links']);
        $this->assertSame('https://s.test/blog/author/jane', $section['groups'][1]['links'][0]['url']);
    }

    public function testHtmlSitemapEmptyWhenNoLinks(): void
    {
        $connection = $this->connection([], ['panth_blog_post']);
        $this->assertSame([], (new HtmlSitemapContributor($this->resource($connection), $this->url(), $this->config()))->getSection());
    }

    private function llms(AdapterInterface $connection, ?Config $config = null, ?StoreVisibility $visibility = null): LlmsTxtContributor
    {
        return new LlmsTxtContributor(
            $this->resource($connection),
            $this->createStub(PostUrlBuilder::class),
            $this->createStub(AuthorUrlBuilder::class),
            $config ?? $this->config(),
            $visibility ?? $this->createStub(StoreVisibility::class)
        );
    }

    public function testLlmsTxtRendersPostList(): void
    {
        $connection = $this->connection(['panth_blog_post' => [
            ['url_key' => 'hello', 'title' => ' Hello ', 'short_description' => '<p>Intro ' . str_repeat('x', 300) . '</p>'],
            ['url_key' => 'bare', 'title' => 'Bare', 'short_description' => null],
            ['url_key' => '', 'title' => 'No slug'],
        ]], ['panth_blog_post']);
        $visibility = $this->createMock(StoreVisibility::class);
        $visibility->expects($this->once())->method('filterPosts')->with($this->anything(), 'panth_blog_post.post_id', 2);

        $out = $this->llms($connection, null, $visibility)->render(2);
        $lines = explode("\n", trim($out));

        $this->assertSame('## Blog Posts', $lines[0]);
        $this->assertStringStartsWith('- [Hello](/blog/hello) - Intro x', $lines[4]);
        $this->assertSame(200, mb_strlen(substr($lines[4], strlen('- [Hello](/blog/hello) - '))));
        $this->assertSame('- [Bare](/blog/bare)', $lines[5]);
        $this->assertCount(6, $lines);
    }

    public function testLlmsTxtDisabledOrEmpty(): void
    {
        $config = $this->createStub(Config::class);
        $config->method('isLlmsTxtIncludeEnabled')->willReturn(false);
        $this->assertSame('', $this->llms($this->connection([], ['panth_blog_post']), $config)->render());

        $this->assertSame('', $this->llms($this->connection([], []))->render());
        $this->assertSame('', $this->llms($this->connection([], ['panth_blog_post']))->render());
        $this->assertSame(75, $this->llms($this->connection([], []))->priority());
    }

    public function testHreflangHasNoAlternates(): void
    {
        $this->assertSame([], (new HreflangContributor())->getAlternates(5));
    }
}
