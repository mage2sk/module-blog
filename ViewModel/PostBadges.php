<?php
declare(strict_types=1);

namespace Panth\Blog\ViewModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Helper\Config;
use Psr\Log\LoggerInterface;

class PostBadges implements ArgumentInterface
{
    private array $cache = [];

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $urlBuilder,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getBadgesForPost($post): array
    {
        if (!$this->config->isShowBadges()) {
            return [];
        }
        if (!is_object($post) || !method_exists($post, 'getId')) {
            return [];
        }
        $postId = (int) $post->getId();
        if ($postId <= 0) {
            return [];
        }
        if (isset($this->cache[$postId])) {
            return $this->cache[$postId];
        }

        $badges = [];
        try {
            if ($this->config->isShowFeaturedBadge()
                && method_exists($post, 'getIsFeatured')
                && (int) $post->getIsFeatured() === 1
            ) {
                $badges[] = ['label' => (string) __('Featured'), 'url' => '', 'tone' => 'featured'];
            }

            if ($this->isNew($post)) {
                $badges[] = ['label' => (string) __('New'), 'url' => '', 'tone' => 'new'];
            }

            $category = $this->fetchPrimaryCategory($postId);
            if ($category !== null) {
                $badges[] = [
                    'label' => $category['name'],
                    'url' => $this->buildTaxonomyUrl('category', $category['url_key']),
                    'tone' => 'category',
                ];
            }

            $tag = $this->fetchFirstTag($postId);
            if ($tag !== null) {
                $badges[] = [
                    'label' => $tag['name'],
                    'url' => $this->buildTaxonomyUrl('tag', $tag['url_key']),
                    'tone' => 'tag',
                ];
            }
        } catch (\Throwable $e) {
            $this->logger->warning(
                '[Panth_Blog] PostBadges::getBadgesForPost failed for post ' . $postId . ': ' . $e->getMessage()
            );
        }

        return $this->cache[$postId] = $badges;
    }

    private function isNew($post): bool
    {
        $days = $this->config->getNewBadgeDays();
        if ($days <= 0 || !method_exists($post, 'getPublishedAt')) {
            return false;
        }
        $published = (string) $post->getPublishedAt();
        if ($published === '') {
            return false;
        }
        $ts = strtotime($published);

        return $ts !== false && $ts >= (time() - ($days * 86400));
    }

    private function fetchPrimaryCategory(int $postId): ?array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['pc' => $this->resource->getTableName('panth_blog_post_category')], [])
            ->join(
                ['c' => $this->resource->getTableName('panth_blog_category')],
                'c.category_id = pc.category_id',
                ['name', 'url_key']
            )
            ->where('pc.post_id = ?', $postId)
            ->where('c.is_active = ?', 1)
            ->order('pc.is_primary DESC')
            ->order('pc.position ASC')
            ->order('c.sort_order ASC')
            ->limit(1);

        $row = $connection->fetchRow($select);

        return $row ?: null;
    }

    private function fetchFirstTag(int $postId): ?array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['pt' => $this->resource->getTableName('panth_blog_post_tag')], [])
            ->join(
                ['t' => $this->resource->getTableName('panth_blog_tag')],
                't.tag_id = pt.tag_id',
                ['name', 'url_key']
            )
            ->where('pt.post_id = ?', $postId)
            ->order('t.name ASC')
            ->limit(1);

        $row = $connection->fetchRow($select);

        return $row ?: null;
    }

    private function buildTaxonomyUrl(string $type, string $slug): string
    {
        $slug = trim((string) $slug, '/');
        if ($slug === '') {
            return '';
        }
        $base = rtrim((string) $this->storeManager->getStore()->getBaseUrl(), '/');

        return $base . '/' . $this->config->getRouteFrontName() . '/' . $type . '/' . $slug;
    }
}
