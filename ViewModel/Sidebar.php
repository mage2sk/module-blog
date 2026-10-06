<?php
declare(strict_types=1);

namespace Panth\Blog\ViewModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Api\CategoryRepositoryInterface;
use Panth\Blog\Api\Data\AuthorInterface;
use Panth\Blog\Api\Data\CategoryInterface;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Api\Data\TagInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Api\TagRepositoryInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Url\AuthorUrlBuilder;
use Panth\Blog\Model\Url\CategoryUrlBuilder;
use Panth\Blog\Model\Url\PostUrlBuilder;
use Panth\Blog\Model\Url\TagUrlBuilder;
use Psr\Log\LoggerInterface;
use Panth\Blog\Model\StoreVisibility;

class Sidebar implements ArgumentInterface
{
    private ?array $recentPosts = null;

    private ?array $categoryTree = null;

    private ?array $tagCloud = null;

    public function __construct(
        private readonly Config $config,
        private readonly PostRepositoryInterface $postRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly TagRepositoryInterface $tagRepository,
        private readonly ResourceConnection $resource,
        private readonly StoreManagerInterface $storeManager,
        private readonly PostUrlBuilder $postUrl,
        private readonly CategoryUrlBuilder $categoryUrl,
        private readonly TagUrlBuilder $tagUrl,
        private readonly AuthorUrlBuilder $authorUrl,
        private readonly LoggerInterface $logger,
        private readonly StoreVisibility $storeVisibility
    ) {
    }

    public function getPostUrl(PostInterface $post): string
    {
        try {
            return $this->postUrl->getPostUrl($post);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] Sidebar::getPostUrl failed: ' . $e->getMessage());
            return '';
        }
    }

    public function getCategoryUrl(CategoryInterface $cat): string
    {
        try {
            return $this->categoryUrl->getCategoryUrl($cat);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] Sidebar::getCategoryUrl failed: ' . $e->getMessage());
            return '';
        }
    }

    public function getTagUrl(TagInterface $tag): string
    {
        try {
            return $this->tagUrl->getTagUrl($tag);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] Sidebar::getTagUrl failed: ' . $e->getMessage());
            return '';
        }
    }

    public function getAuthorUrl(AuthorInterface $author): string
    {
        try {
            return $this->authorUrl->getAuthorUrl($author);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] Sidebar::getAuthorUrl failed: ' . $e->getMessage());
            return '';
        }
    }

    public function isShowCategories(): bool
    {
        try {
            return $this->config->isSidebarShowCategories();
        } catch (\Throwable) {
            return false;
        }
    }

    public function isShowTagCloud(): bool
    {
        try {
            return $this->config->isSidebarShowTagCloud();
        } catch (\Throwable) {
            return false;
        }
    }

    public function isShowSearch(): bool
    {
        try {
            return $this->config->isSidebarShowSearch();
        } catch (\Throwable) {
            return false;
        }
    }

    public function isShowSubscribe(): bool
    {
        try {
            return $this->config->isSidebarShowSubscribe();
        } catch (\Throwable) {
            return false;
        }
    }

    public function isShowRecent(): bool
    {
        try {
            return $this->config->getSidebarRecentCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function getRecentPosts(): array
    {
        if ($this->recentPosts !== null) {
            return $this->recentPosts;
        }
        $this->recentPosts = [];
        $limit = max(0, $this->config->getSidebarRecentCount());
        if ($limit === 0) {
            return $this->recentPosts;
        }
        try {
            $conn = $this->resource->getConnection();
            $postTable = $this->resource->getTableName('panth_blog_post');
            $select = $conn->select()
                ->from($postTable, ['post_id'])
                ->where('status = ?', PostInterface::STATUS_PUBLISHED)
                ->order('published_at DESC')
                ->order('post_id DESC')
                ->limit($limit);
            $this->storeVisibility->filterPosts($select, $postTable . '.post_id');
            $ids = $conn->fetchCol($select);
            foreach ($ids as $id) {
                try {
                    $this->recentPosts[] = $this->postRepository->getById((int) $id);
                } catch (\Throwable $e) {
                    $this->logger->info('[Panth_Blog] Sidebar::getRecentPosts skip ' . $id . ': ' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] Sidebar::getRecentPosts failed: ' . $e->getMessage());
        }
        return $this->recentPosts;
    }

    public function getCategoryTree(): array
    {
        if ($this->categoryTree !== null) {
            return $this->categoryTree;
        }
        $this->categoryTree = [];
        try {
            $conn = $this->resource->getConnection();
            $table = $this->resource->getTableName('panth_blog_category');
            $select = $conn->select()
                ->from($table, ['category_id', 'parent_id', 'level', 'url_key', 'name', 'post_count'])
                ->where('is_active = ?', 1)
                ->order('level ASC')
                ->order('sort_order ASC')
                ->order('name ASC');
            $this->storeVisibility->filterCategories($select, $table . '.category_id');
            $rows = $conn->fetchAll($select);
            foreach ($rows as $row) {
                $this->categoryTree[] = [
                    'id'         => (int) $row['category_id'],
                    'name'       => (string) ($row['name'] ?? ''),
                    'url'        => $this->buildCategoryUrlFromKey((string) ($row['url_key'] ?? '')),
                    'level'      => (int) ($row['level'] ?? 1),
                    'post_count' => (int) ($row['post_count'] ?? 0),
                ];
            }
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] Sidebar::getCategoryTree failed: ' . $e->getMessage());
        }
        return $this->categoryTree;
    }

    public function getTagCloud(): array
    {
        if ($this->tagCloud !== null) {
            return $this->tagCloud;
        }
        $this->tagCloud = [];
        try {
            $threshold = max(0, $this->config->getTagThinThreshold());
            $conn = $this->resource->getConnection();
            $table = $this->resource->getTableName('panth_blog_tag');
            $select = $conn->select()
                ->from($table, ['tag_id', 'url_key', 'name', 'post_count'])
                ->where('post_count >= ?', $threshold)
                ->order('post_count DESC')
                ->order('name ASC');
            $rows = $conn->fetchAll($select);
            if (empty($rows)) {
                return $this->tagCloud;
            }
            $maxScore = 0.0;
            $scores = [];
            foreach ($rows as $row) {
                $count = (int) ($row['post_count'] ?? 0);
                $score = log($count + 1);
                $scores[(int) $row['tag_id']] = $score;
                if ($score > $maxScore) {
                    $maxScore = $score;
                }
            }
            foreach ($rows as $row) {
                $tagId = (int) $row['tag_id'];
                $count = (int) ($row['post_count'] ?? 0);
                $scale = ($maxScore > 0.0) ? round(($scores[$tagId] ?? 0.0) / $maxScore, 3) : 0.0;
                $this->tagCloud[] = [
                    'url'   => $this->buildTagUrlFromKey((string) ($row['url_key'] ?? '')),
                    'name'  => (string) ($row['name'] ?? ''),
                    'count' => $count,
                    'scale' => (float) $scale,
                ];
            }
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] Sidebar::getTagCloud failed: ' . $e->getMessage());
        }
        return $this->tagCloud;
    }

    public function isFeedAvailable(): bool
    {
        try {
            return $this->config->isEnabled() && $this->config->isFeedEnabled();
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] Sidebar::isFeedAvailable failed: ' . $e->getMessage());
            return false;
        }
    }

    public function getRouteFrontName(): string
    {
        return trim($this->config->getRouteFrontName(), '/');
    }

    private function buildCategoryUrlFromKey(string $urlKey): string
    {
        if ($urlKey === '') {
            return '';
        }
        try {
            $base = (string) $this->storeManager->getStore()->getBaseUrl();
            return rtrim($base, '/') . '/' . $this->config->getRouteFrontName() . '/category/' . $urlKey;
        } catch (\Throwable) {
            return '/' . $this->config->getRouteFrontName() . '/category/' . $urlKey;
        }
    }

    private function buildTagUrlFromKey(string $urlKey): string
    {
        if ($urlKey === '') {
            return '';
        }
        try {
            $base = (string) $this->storeManager->getStore()->getBaseUrl();
            return rtrim($base, '/') . '/' . $this->config->getRouteFrontName() . '/tag/' . $urlKey;
        } catch (\Throwable) {
            return '/' . $this->config->getRouteFrontName() . '/tag/' . $urlKey;
        }
    }
}
