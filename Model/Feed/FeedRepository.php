<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Feed;

use Magento\Framework\App\ResourceConnection;
use Panth\Blog\Model\Cache\Type\Feed as FeedCache;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Api\AuthorRepositoryInterface;
use Panth\Blog\Api\CategoryRepositoryInterface;
use Panth\Blog\Api\Data\AuthorInterface;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Api\TagRepositoryInterface;
use Panth\Blog\Helper\Config;
use Psr\Log\LoggerInterface;
use Panth\Blog\Model\StoreVisibility;

class FeedRepository
{
    private const CACHE_TAG = FeedCache::CACHE_TAG;

    private array $authorCache = [];

    public function __construct(
        private readonly RssBuilder $rssBuilder,
        private readonly AtomBuilder $atomBuilder,
        private readonly PostRepositoryInterface $postRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly TagRepositoryInterface $tagRepository,
        private readonly AuthorRepositoryInterface $authorRepository,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly ResourceConnection $resource,
        private readonly FeedCache $cache,
        private readonly LoggerInterface $logger,
        private readonly StoreVisibility $storeVisibility
    ) {
    }

    public function buildSiteWideRss(int $storeId): string
    {
        return $this->cached(
            'feed_site_rss_' . $storeId,
            function () use ($storeId): string {
                $posts = $this->loadPosts($storeId, null, null, null);
                return $this->rssBuilder->buildSiteWideRss($storeId, $posts, $this->authorResolver());
            },
            $storeId
        );
    }

    public function buildSiteWideAtom(int $storeId): string
    {
        return $this->cached(
            'feed_site_atom_' . $storeId,
            function () use ($storeId): string {
                $posts = $this->loadPosts($storeId, null, null, null);
                return $this->atomBuilder->buildSiteWideAtom($storeId, $posts, $this->authorResolver());
            },
            $storeId
        );
    }

    public function buildCategoryRss(int $categoryId, int $storeId): string
    {
        if ($categoryId <= 0) {
            return '';
        }
        return $this->cached(
            'feed_category_rss_' . $storeId . '_' . $categoryId,
            function () use ($categoryId, $storeId): string {
                try {
                    $category = $this->categoryRepository->getById($categoryId);
                } catch (NoSuchEntityException) {
                    return '';
                }
                $posts = $this->loadPosts($storeId, $categoryId, null, null);
                return $this->rssBuilder->buildCategoryRss($storeId, $posts, $category, $this->authorResolver());
            },
            $storeId
        );
    }

    public function buildTagRss(int $tagId, int $storeId): string
    {
        if ($tagId <= 0) {
            return '';
        }
        return $this->cached(
            'feed_tag_rss_' . $storeId . '_' . $tagId,
            function () use ($tagId, $storeId): string {
                try {
                    $tag = $this->tagRepository->getById($tagId);
                } catch (NoSuchEntityException) {
                    return '';
                }
                $posts = $this->loadPosts($storeId, null, $tagId, null);
                return $this->rssBuilder->buildTagRss($storeId, $posts, $tag, $this->authorResolver());
            },
            $storeId
        );
    }

    public function buildAuthorRss(int $authorId, int $storeId): string
    {
        if ($authorId <= 0) {
            return '';
        }
        return $this->cached(
            'feed_author_rss_' . $storeId . '_' . $authorId,
            function () use ($authorId, $storeId): string {
                try {
                    $author = $this->authorRepository->getById($authorId);
                } catch (NoSuchEntityException) {
                    return '';
                }
                $posts = $this->loadPosts($storeId, null, null, $authorId);
                return $this->rssBuilder->buildAuthorRss($storeId, $posts, $author, $this->authorResolver());
            },
            $storeId
        );
    }

    public function invalidate(): void
    {
        try {
            $this->cache->clean();
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] FeedRepository::invalidate failed: ' . $e->getMessage());
        }
    }

    private function loadPosts(int $storeId, ?int $categoryId, ?int $tagId, ?int $authorId): array
    {
        $limit = max(1, $this->config->getFeedsPostsPerFeed($storeId));
        try {
            $conn = $this->resource->getConnection();
            $postTable = $this->resource->getTableName('panth_blog_post');
            $select = $conn->select()
                ->from(['p' => $postTable], ['post_id'])
                ->where('p.status = ?', PostInterface::STATUS_PUBLISHED)
                ->order('p.published_at DESC')
                ->order('p.post_id DESC')
                ->limit($limit);

            if ($categoryId !== null && $categoryId > 0) {
                $linkTable = $this->resource->getTableName('panth_blog_post_category');
                $select->join(['lc' => $linkTable], 'lc.post_id = p.post_id', [])
                    ->where('lc.category_id = ?', $categoryId);
            }
            if ($tagId !== null && $tagId > 0) {
                $linkTable = $this->resource->getTableName('panth_blog_post_tag');
                $select->join(['lt' => $linkTable], 'lt.post_id = p.post_id', [])
                    ->where('lt.tag_id = ?', $tagId);
            }
            if ($authorId !== null && $authorId > 0) {
                $select->where('p.author_id = ?', $authorId);
            }

            $this->storeVisibility->filterPosts($select, 'p.post_id', $storeId);
            $ids = $conn->fetchCol($select);
            $posts = [];
            foreach ($ids as $id) {
                try {
                    $posts[] = $this->postRepository->getById((int) $id);
                } catch (NoSuchEntityException) {
                    continue;
                }
            }
            return $posts;
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] FeedRepository::loadPosts failed: ' . $e->getMessage());
            return [];
        }
    }

    private function authorResolver(): callable
    {
        return function (PostInterface $post): ?AuthorInterface {
            $authorId = (int) ($post->getAuthorId() ?? 0);
            if ($authorId <= 0) {
                return null;
            }
            if (array_key_exists($authorId, $this->authorCache)) {
                $cached = $this->authorCache[$authorId];
                return $cached === false ? null : $cached;
            }
            try {
                $author = $this->authorRepository->getById($authorId);
                $this->authorCache[$authorId] = $author;
                return $author;
            } catch (NoSuchEntityException) {
                $this->authorCache[$authorId] = false;
                return null;
            }
        };
    }

    private function cached(string $key, callable $builder, int $storeId): string
    {
        try {
            $hit = $this->cache->load($key);
            if (is_string($hit) && $hit !== '') {
                return $hit;
            }
            $xml = $builder();
            if ($xml === '') {
                return '';
            }
            $ttl = max(60, $this->config->getFeedsCacheTtl($storeId));
            $this->cache->save($xml, $key, [self::CACHE_TAG], $ttl);
            return $xml;
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] FeedRepository::cached(' . $key . ') failed: ' . $e->getMessage());
            return '';
        }
    }
}
