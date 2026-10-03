<?php
declare(strict_types=1);

namespace Panth\Blog\ViewModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Schema\JsonLdRenderer;
use Panth\Blog\Model\Url\PostUrlBuilder;
use Psr\Log\LoggerInterface;
use Panth\Blog\Model\StoreVisibility;

class BlogIndex implements ArgumentInterface
{
    private const REGISTRY_KEY = 'panth_blog_current_page';

    private ?array $posts = null;
    private ?int $totalPosts = null;

    public function __construct(
        private readonly Registry $registry,
        private readonly Config $config,
        private readonly PostRepositoryInterface $postRepository,
        private readonly ResourceConnection $resource,
        private readonly JsonLdRenderer $jsonLd,
        private readonly StoreManagerInterface $storeManager,
        private readonly PostUrlBuilder $postUrl,
        private readonly LoggerInterface $logger,
        private readonly StoreVisibility $storeVisibility
    ) {
    }

    public function getCurrentPage(): int
    {
        $registered = $this->registry->registry(self::REGISTRY_KEY);
        if (is_numeric($registered)) {
            $page = (int) $registered;
            return $page > 0 ? $page : 1;
        }
        return 1;
    }

    public function getPostsPerPage(): int
    {
        return $this->config->getPostsPerPage();
    }

    public function getPosts(): array
    {
        if ($this->posts !== null) {
            return $this->posts;
        }
        $this->posts = [];
        $page = $this->getCurrentPage();
        $perPage = $this->getPostsPerPage();
        $offset = max(0, ($page - 1) * $perPage);
        try {
            $conn = $this->resource->getConnection();
            $postTable = $this->resource->getTableName('panth_blog_post');
            $select = $conn->select()
                ->from($postTable, ['post_id'])
                ->where('status = ?', PostInterface::STATUS_PUBLISHED)
                ->order('published_at DESC')
                ->order('post_id DESC')
                ->limit($perPage, $offset);
            $this->storeVisibility->filterPosts($select, $postTable . '.post_id');
            $ids = $conn->fetchCol($select);
            foreach ($ids as $id) {
                try {
                    $this->posts[] = $this->postRepository->getById((int) $id);
                } catch (\Throwable $e) {
                    $this->logger->info('[Panth_Blog] BlogIndex::getPosts skip ' . $id . ': ' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] BlogIndex::getPosts failed: ' . $e->getMessage());
        }
        return $this->posts;
    }

    public function getTotalPosts(): int
    {
        if ($this->totalPosts !== null) {
            return $this->totalPosts;
        }
        try {
            $conn = $this->resource->getConnection();
            $postTable = $this->resource->getTableName('panth_blog_post');
            $select = $conn->select()
                ->from($postTable, ['cnt' => new \Zend_Db_Expr('COUNT(post_id)')])
                ->where('status = ?', PostInterface::STATUS_PUBLISHED);
            $this->storeVisibility->filterPosts($select, $postTable . '.post_id');
            return $this->totalPosts = (int) $conn->fetchOne($select);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] BlogIndex::getTotalPosts failed: ' . $e->getMessage());
            return $this->totalPosts = 0;
        }
    }

    public function getPostUrl(PostInterface $post): string
    {
        try {
            return $this->postUrl->getPostUrl($post);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] BlogIndex::getPostUrl failed: ' . $e->getMessage());
            return '';
        }
    }

    public function getMediaUrl(string $path): string
    {
        if ($path === '') {
            return '';
        }
        if (stripos($path, 'http://') === 0 || stripos($path, 'https://') === 0) {
            return $path;
        }
        try {
            $base = (string) $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
            return rtrim($base, '/') . '/blog/' . ltrim($path, '/');
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] BlogIndex::getMediaUrl failed: ' . $e->getMessage());
            return $path;
        }
    }

    public function getPageUrl(int $page): string
    {
        try {
            return $this->postUrl->getPaginatedIndexUrl($page);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] BlogIndex::getPageUrl failed: ' . $e->getMessage());
            return '';
        }
    }

    public function getIndexTitle(): string
    {
        $title = trim($this->config->getIndexTitle());
        return $title !== '' ? $title : 'Blog';
    }

    public function getHeroText(): string
    {
        return $this->config->getIndexHeroText();
    }

    public function isShowReadingTime(): bool
    {
        try {
            return $this->config->isShowReadingTime();
        } catch (\Throwable) {
            return true;
        }
    }

    public function getJsonLd(): string
    {
        try {
            $baseUrl = (string) $this->storeManager->getStore()->getBaseUrl();
            $url = rtrim($baseUrl, '/') . '/' . $this->config->getRouteFrontName();
            $items = [];
            $page = $this->getCurrentPage();
            $perPage = $this->getPostsPerPage();
            $position = ($page - 1) * $perPage + 1;
            foreach ($this->getPosts() as $post) {
                $items[] = [
                    '@type'    => 'ListItem',
                    'position' => $position++,
                    'url'      => $this->postUrl->getPostUrl($post),
                    'name'     => $post->getTitle(),
                ];
            }
            $breadcrumbs = [
                ['name' => 'Home', 'item' => rtrim($baseUrl, '/') . '/'],
                ['name' => $this->getIndexTitle(), 'item' => $url],
            ];
            return $this->jsonLd->renderForBlogIndex(
                $this->getIndexTitle(),
                $url,
                $this->config->getIndexDescription(),
                $items,
                $breadcrumbs
            );
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] BlogIndex::getJsonLd failed: ' . $e->getMessage());
            return '';
        }
    }
}
