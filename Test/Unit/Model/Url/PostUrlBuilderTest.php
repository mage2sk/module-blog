<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model\Url;

use Magento\Framework\UrlInterface;
use Panth\Blog\Api\Data\AuthorInterface;
use Panth\Blog\Api\Data\CategoryInterface;
use Panth\Blog\Api\Data\TagInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Post;
use Panth\Blog\Model\Url\AuthorUrlBuilder;
use Panth\Blog\Model\Url\CategoryUrlBuilder;
use Panth\Blog\Model\Url\FeedUrlBuilder;
use Panth\Blog\Model\Url\PostUrlBuilder;
use Panth\Blog\Model\Url\TagUrlBuilder;
use PHPUnit\Framework\TestCase;

class PostUrlBuilderTest extends TestCase
{
    private function url(): UrlInterface
    {
        $url = $this->createStub(UrlInterface::class);
        $url->method('getDirectUrl')->willReturnCallback(static function (string $path, array $params = []) {
            $query = isset($params['_query']) ? '?' . http_build_query($params['_query']) : '';
            return 'https://shop.test/' . $path . $query;
        });
        return $url;
    }

    private function config(string $front): Config
    {
        $config = $this->createStub(Config::class);
        $config->method('getRouteFrontName')->willReturn($front);
        return $config;
    }

    private function post(string $urlKey, int $id): Post
    {
        $post = $this->createStub(Post::class);
        $post->method('getUrlKey')->willReturn($urlKey);
        $post->method('getId')->willReturn($id);
        return $post;
    }

    public function testPostUrlUsesUrlKeyAndTrimsFrontName(): void
    {
        $builder = new PostUrlBuilder($this->url(), $this->config('/news/'));
        $this->assertSame('https://shop.test/news/hello', $builder->getPostUrl($this->post('hello', 3)));
    }

    public function testPostWithoutUrlKeyFallsBackToIdRoute(): void
    {
        $builder = new PostUrlBuilder($this->url(), $this->config('blog'));
        $this->assertSame('https://shop.test/blog/post/view?id=9', $builder->getPostUrl($this->post('', 9)));
    }

    public function testEmptyFrontNameUsesDefault(): void
    {
        $builder = new PostUrlBuilder($this->url(), $this->config(''));
        $this->assertSame('https://shop.test/blog', $builder->getPaginatedIndexUrl(1));
    }

    public function testPaginatedIndexUrl(): void
    {
        $builder = new PostUrlBuilder($this->url(), $this->config('blog'));
        $this->assertSame('https://shop.test/blog', $builder->getPaginatedIndexUrl(0));
        $this->assertSame('https://shop.test/blog/page/2', $builder->getPaginatedIndexUrl(2));
    }

    public function testTaxonomyUrls(): void
    {
        $category = $this->createStub(CategoryInterface::class);
        $category->method('getUrlKey')->willReturn('news');
        $tag = $this->createStub(TagInterface::class);
        $tag->method('getUrlKey')->willReturn('php');
        $author = $this->createStub(AuthorInterface::class);
        $author->method('getUrlKey')->willReturn('jane');

        $categories = new CategoryUrlBuilder($this->url(), $this->config('blog'));
        $tags = new TagUrlBuilder($this->url(), $this->config('blog/'));
        $authors = new AuthorUrlBuilder($this->url(), $this->config(''));

        $this->assertSame('https://shop.test/blog/category/news', $categories->getCategoryUrl($category));
        $this->assertSame('https://shop.test/blog/category/news', $categories->getPaginatedCategoryUrl($category, 1));
        $this->assertSame('https://shop.test/blog/category/news/page/3', $categories->getPaginatedCategoryUrl($category, 3));
        $this->assertSame('https://shop.test/blog/tag/php', $tags->getTagUrl($tag));
        $this->assertSame('https://shop.test/blog/tag/php/page/2', $tags->getPaginatedTagUrl($tag, 2));
        $this->assertSame('https://shop.test/blog/author/jane', $authors->getAuthorUrl($author));
        $this->assertSame('https://shop.test/blog/author/jane', $authors->getPaginatedAuthorUrl($author, -1));
        $this->assertSame('https://shop.test/blog/author/jane/page/4', $authors->getPaginatedAuthorUrl($author, 4));
    }

    public function testFeedUrls(): void
    {
        $category = $this->createStub(CategoryInterface::class);
        $category->method('getUrlKey')->willReturn('news');
        $tag = $this->createStub(TagInterface::class);
        $tag->method('getUrlKey')->willReturn('php');
        $author = $this->createStub(AuthorInterface::class);
        $author->method('getUrlKey')->willReturn('jane');

        $feeds = new FeedUrlBuilder($this->url(), $this->config('journal'));
        $this->assertSame('https://shop.test/journal/feed.xml', $feeds->getMainRssUrl());
        $this->assertSame('https://shop.test/journal/feed/atom.xml', $feeds->getMainAtomUrl());
        $this->assertSame('https://shop.test/journal/feed/category/news.xml', $feeds->getCategoryRssUrl($category));
        $this->assertSame('https://shop.test/journal/feed/tag/php.xml', $feeds->getTagRssUrl($tag));
        $this->assertSame('https://shop.test/journal/feed/author/jane.xml', $feeds->getAuthorRssUrl($author));
    }

    public function testCanonicalUrlAcceptsOnlyHttpOrSitePaths(): void
    {
        $builder = new PostUrlBuilder($this->url(), $this->config('blog'));
        $make = function (string $canonical): Post {
            $post = $this->createStub(Post::class);
            $post->method('getUrlKey')->willReturn('hello');
            $post->method('getCanonicalUrl')->willReturn($canonical);
            return $post;
        };
        $this->assertSame('https://other.test/x', $builder->getCanonicalUrl($make('https://other.test/x')));
        $this->assertSame('https://shop.test/guides/x', $builder->getCanonicalUrl($make('/guides/x')));
        $this->assertSame('https://shop.test/blog/hello', $builder->getCanonicalUrl($make('javascript:alert(1)')));
        $this->assertSame('https://shop.test/blog/hello', $builder->getCanonicalUrl($make('//evil.test')));
        $this->assertSame('https://shop.test/blog/hello', $builder->getCanonicalUrl($make('')));
    }
}
