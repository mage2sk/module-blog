<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model\Feed;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Api\AuthorRepositoryInterface;
use Panth\Blog\Api\CategoryRepositoryInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Api\TagRepositoryInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Author;
use Panth\Blog\Model\Cache\Type\Feed as FeedCache;
use Panth\Blog\Model\Category;
use Panth\Blog\Model\Feed\AtomBuilder;
use Panth\Blog\Model\Feed\FeedRepository;
use Panth\Blog\Model\Feed\RssBuilder;
use Panth\Blog\Model\Post;
use Panth\Blog\Model\StoreVisibility;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class FeedRepositoryTest extends TestCase
{
    use BlogTestHelpers;

    private RssBuilder $rss;
    private AtomBuilder $atom;
    private FeedCache $cache;
    private PostRepositoryInterface $posts;
    private CategoryRepositoryInterface $categories;
    private AuthorRepositoryInterface $authors;
    private AdapterInterface $connection;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->rss = $this->createStub(RssBuilder::class);
        $this->atom = $this->createStub(AtomBuilder::class);
        $this->cache = $this->createStub(FeedCache::class);
        $this->posts = $this->createStub(PostRepositoryInterface::class);
        $this->categories = $this->createStub(CategoryRepositoryInterface::class);
        $this->authors = $this->createStub(AuthorRepositoryInterface::class);
        $this->connection = $this->connectionStub();
        $this->logger = $this->createStub(LoggerInterface::class);
    }

    private function repository(): FeedRepository
    {
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $config = $this->createStub(Config::class);
        $config->method('getFeedsPostsPerFeed')->willReturn(10);
        $config->method('getFeedsCacheTtl')->willReturn(5);

        $visibility = $this->createStub(StoreVisibility::class);
        $visibility->method('filterPosts')->willReturnArgument(0);

        return new FeedRepository(
            $this->rss,
            $this->atom,
            $this->posts,
            $this->categories,
            $this->createStub(TagRepositoryInterface::class),
            $this->authors,
            $config,
            $this->createStub(StoreManagerInterface::class),
            $resource,
            $this->cache,
            $this->logger,
            $visibility
        );
    }

    public function testCacheHitShortCircuitsBuilder(): void
    {
        $this->cache = $this->createMock(FeedCache::class);
        $this->cache->expects($this->once())->method('load')->with('feed_site_rss_2')->willReturn('<cached/>');
        $this->cache->expects($this->never())->method('save');
        $this->rss = $this->createMock(RssBuilder::class);
        $this->rss->expects($this->never())->method('buildSiteWideRss');

        $this->assertSame('<cached/>', $this->repository()->buildSiteWideRss(2));
    }

    public function testCacheMissBuildsLoadsPostsAndStoresWithMinimumTtl(): void
    {
        $this->connection->method('fetchCol')->willReturn(['1', '2']);
        $post1 = $this->makeModel(Post::class, ['author_id' => 7]);
        $post2 = $this->makeModel(Post::class, ['author_id' => 7]);
        $this->posts->method('getById')->willReturnCallback(static function (int $id) use ($post1) {
            if ($id === 2) {
                throw new NoSuchEntityException();
            }
            return $post1;
        });

        $author = $this->makeModel(Author::class, ['display_name' => 'Jane']);
        $this->authors = $this->createMock(AuthorRepositoryInterface::class);
        $this->authors->expects($this->once())->method('getById')->with(7)->willReturn($author);

        $this->rss = $this->createMock(RssBuilder::class);
        $this->rss->expects($this->once())->method('buildSiteWideRss')
            ->willReturnCallback(function (int $storeId, array $posts, callable $resolver) use ($post1, $post2, $author) {
                $this->assertSame(3, $storeId);
                $this->assertSame([$post1], $posts);
                $this->assertSame($author, $resolver($post1));
                $this->assertSame($author, $resolver($post2));
                return '<rss/>';
            });

        $this->cache = $this->createMock(FeedCache::class);
        $this->cache->method('load')->willReturn(false);
        $this->cache->expects($this->once())->method('save')->with('<rss/>', 'feed_site_rss_3', ['PANTH_BLOG_FEED'], 60);

        $this->assertSame('<rss/>', $this->repository()->buildSiteWideRss(3));
    }

    public function testAuthorResolverCachesMisses(): void
    {
        $this->connection->method('fetchCol')->willReturn([]);
        $this->authors = $this->createMock(AuthorRepositoryInterface::class);
        $this->authors->expects($this->once())->method('getById')->willThrowException(new NoSuchEntityException());
        $this->atom = $this->createStub(AtomBuilder::class);
        $this->atom->method('buildSiteWideAtom')->willReturnCallback(function ($s, $p, callable $resolver) {
            $post = $this->makeModel(Post::class, ['author_id' => 9]);
            $this->assertNull($resolver($post));
            $this->assertNull($resolver($post));
            $this->assertNull($resolver($this->makeModel(Post::class)));
            return '<feed/>';
        });

        $this->assertSame('<feed/>', $this->repository()->buildSiteWideAtom(1));
    }

    public function testTaxonomyFeedsRejectInvalidIdsAndMissingEntities(): void
    {
        $repository = $this->repository();
        $this->assertSame('', $repository->buildCategoryRss(0, 1));
        $this->assertSame('', $repository->buildTagRss(-1, 1));
        $this->assertSame('', $repository->buildAuthorRss(0, 1));

        $this->categories->method('getById')->willThrowException(new NoSuchEntityException());
        $this->cache = $this->createMock(FeedCache::class);
        $this->cache->method('load')->willReturn(null);
        $this->cache->expects($this->never())->method('save');
        $this->assertSame('', $this->repository()->buildCategoryRss(5, 1));
    }

    public function testCategoryFeedBuildsWithCategory(): void
    {
        $category = $this->makeModel(Category::class, ['name' => 'News']);
        $this->categories->method('getById')->willReturn($category);
        $this->connection->method('fetchCol')->willReturn([]);
        $this->rss = $this->createMock(RssBuilder::class);
        $this->rss->expects($this->once())->method('buildCategoryRss')
            ->with(1, [], $category, $this->isCallable())
            ->willReturn('<rss/>');

        $this->assertSame('<rss/>', $this->repository()->buildCategoryRss(5, 1));
    }

    public function testLoadPostsFailureLogsAndYieldsEmptyList(): void
    {
        $this->connection->method('fetchCol')->willThrowException(new \RuntimeException('sql'));
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger->expects($this->once())->method('warning')->with($this->stringContains('loadPosts failed: sql'));
        $this->rss->method('buildSiteWideRss')->willReturnCallback(function ($s, array $posts) {
            $this->assertSame([], $posts);
            return '<rss/>';
        });

        $this->assertSame('<rss/>', $this->repository()->buildSiteWideRss(1));
    }

    public function testInvalidateCleansCacheAndLogsFailures(): void
    {
        $this->cache = $this->createMock(FeedCache::class);
        $this->cache->expects($this->once())->method('clean')->willThrowException(new \RuntimeException('locked'));
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger->expects($this->once())->method('warning')->with($this->stringContains('locked'));
        $this->repository()->invalidate();
    }
}
