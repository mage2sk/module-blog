<?php
declare(strict_types=1);

namespace Panth\Blog\ViewModel;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Api\CategoryRepositoryInterface;
use Panth\Blog\Api\Data\CategoryInterface;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Schema\JsonLdRenderer;
use Panth\Blog\Model\Url\CategoryUrlBuilder;
use Panth\Blog\Model\Url\PostUrlBuilder;
use Psr\Log\LoggerInterface;
use Panth\Blog\Model\StoreVisibility;

class CategoryView implements ArgumentInterface
{
    private const REGISTRY_KEY = 'current_panth_blog_category';

    private bool $categoryLoaded = false;
    private ?CategoryInterface $category = null;

    private array $pagesCache = [];
    private ?int $totalPosts = null;

    public function __construct(
        private readonly Registry $registry,
        private readonly Config $config,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly PostRepositoryInterface $postRepository,
        private readonly JsonLdRenderer $jsonLd,
        private readonly StoreManagerInterface $storeManager,
        private readonly ResourceConnection $resource,
        private readonly RequestInterface $request,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        private readonly CategoryUrlBuilder $categoryUrl,
        private readonly PostUrlBuilder $postUrl,
        private readonly LoggerInterface $logger,
        private readonly StoreVisibility $storeVisibility
    ) {
    }

    public function getCategory(): ?CategoryInterface
    {
        if ($this->categoryLoaded) {
            return $this->category;
        }
        $this->categoryLoaded = true;
        $candidate = $this->registry->registry(self::REGISTRY_KEY);
        $this->category = $candidate instanceof CategoryInterface ? $candidate : null;
        return $this->category;
    }

    public function getPosts(): array
    {
        $page = max(1, $this->getCurrentPage());
        $pageSize = max(1, $this->getPostsPerPage());
        $cacheKey = $page . ':' . $pageSize;
        if (isset($this->pagesCache[$cacheKey])) {
            return $this->pagesCache[$cacheKey];
        }
        $ids = $this->fetchPostIds($page, $pageSize);
        $posts = [];
        foreach ($ids as $id) {
            try {
                $posts[] = $this->postRepository->getById((int) $id);
            } catch (\Throwable $e) {
                $this->logger->info('[Panth_Blog] CategoryView::getPosts skip ' . $id . ': ' . $e->getMessage());
            }
        }
        return $this->pagesCache[$cacheKey] = $posts;
    }

    public function getPostUrl(PostInterface $post): string
    {
        try {
            return $this->postUrl->getPostUrl($post);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] CategoryView::getPostUrl failed: ' . $e->getMessage());
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
            $this->logger->warning('[Panth_Blog] CategoryView::getMediaUrl failed: ' . $e->getMessage());
            return $path;
        }
    }

    public function getPageUrl(int $page): string
    {
        $cat = $this->getCategory();
        if ($cat === null) {
            return '';
        }
        try {
            return $this->categoryUrl->getPaginatedCategoryUrl($cat, $page);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] CategoryView::getPageUrl failed: ' . $e->getMessage());
            return '';
        }
    }

    public function getCategoryUrl(CategoryInterface $cat): string
    {
        try {
            return $this->categoryUrl->getCategoryUrl($cat);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] CategoryView::getCategoryUrl failed: ' . $e->getMessage());
            return '';
        }
    }

    public function getTotalPosts(): int
    {
        if ($this->totalPosts !== null) {
            return $this->totalPosts;
        }
        $cat = $this->getCategory();
        if ($cat === null || $cat->getCategoryId() === null) {
            return $this->totalPosts = 0;
        }
        try {
            $conn = $this->resource->getConnection();
            $linkTable = $this->resource->getTableName('panth_blog_post_category');
            $postTable = $this->resource->getTableName('panth_blog_post');
            $select = $conn->select()
                ->from(['p' => $postTable], ['cnt' => new \Zend_Db_Expr('COUNT(DISTINCT p.post_id)')])
                ->join(['l' => $linkTable], 'l.post_id = p.post_id', [])
                ->where('l.category_id = ?', (int) $cat->getCategoryId())
                ->where('p.status = ?', PostInterface::STATUS_PUBLISHED);
            $this->storeVisibility->filterPosts($select, 'p.post_id');
            return $this->totalPosts = (int) $conn->fetchOne($select);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] CategoryView::getTotalPosts failed: ' . $e->getMessage());
            return $this->totalPosts = 0;
        }
    }

    public function getCurrentPage(): int
    {
        $registered = $this->registry->registry('panth_blog_current_page');
        if (is_numeric($registered)) {
            $page = (int) $registered;
            if ($page > 0) {
                return $page;
            }
        }
        $raw = $this->request->getParam('page', $this->request->getParam('p', 1));
        if (!is_numeric($raw)) {
            return 1;
        }
        $page = (int) $raw;
        return $page > 0 ? $page : 1;
    }

    public function getPostsPerPage(): int
    {
        $cat = $this->getCategory();
        if ($cat !== null) {
            $override = $cat->getPostsPerPage();
            if ($override !== null && $override > 0) {
                return (int) $override;
            }
        }
        return $this->config->getPostsPerPage();
    }

    public function getJsonLd(): string
    {
        $cat = $this->getCategory();
        if ($cat === null) {
            return '';
        }
        try {
            $page = $this->getCurrentPage();
            $perPage = $this->getPostsPerPage();
            $posts = $this->getPosts();
            $items = [];
            $position = ($page - 1) * $perPage + 1;
            foreach ($posts as $post) {
                $items[] = [
                    '@type'    => 'ListItem',
                    'position' => $position++,
                    'url'      => $this->postUrl->getPostUrl($post),
                    'name'     => $post->getTitle(),
                ];
            }
            $url = $this->categoryUrl->getCategoryUrl($cat);
            $baseUrl = (string) $this->storeManager->getStore()->getBaseUrl();
            $breadcrumbs = [
                ['name' => 'Home', 'item' => $baseUrl],
                ['name' => 'Blog', 'item' => rtrim($baseUrl, '/') . '/' . $this->config->getRouteFrontName()],
                ['name' => $cat->getName(), 'item' => $url],
            ];
            return $this->jsonLd->renderForCategory($cat, $url, $items, $breadcrumbs, $this->getTotalPosts());
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] CategoryView::getJsonLd failed: ' . $e->getMessage());
            return '';
        }
    }

    public function getEffectiveTemplate(): string
    {
        $allowed = ['grid', 'list', 'magazine'];
        $configured = strtolower(trim($this->config->getCategoryTemplate()));
        $cat = $this->getCategory();
        if ($cat !== null) {
            $override = strtolower(trim((string) ($cat->getTemplate() ?? '')));
            if ($override !== '' && $override !== 'grid' && in_array($override, $allowed, true)) {
                return $override;
            }
        }
        return in_array($configured, $allowed, true) ? $configured : 'grid';
    }

    public function isShowReadingTime(): bool
    {
        try {
            return $this->config->isShowReadingTime();
        } catch (\Throwable) {
            return true;
        }
    }

    public function getCanonicalUrl(): string
    {
        $cat = $this->getCategory();
        if ($cat === null) {
            return '';
        }
        try {
            return $this->categoryUrl->getCategoryUrl($cat);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] CategoryView::getCanonicalUrl failed: ' . $e->getMessage());
            return '';
        }
    }

    private function fetchPostIds(int $page, int $pageSize): array
    {
        $cat = $this->getCategory();
        if ($cat === null || $cat->getCategoryId() === null) {
            return [];
        }
        try {
            $conn = $this->resource->getConnection();
            $linkTable = $this->resource->getTableName('panth_blog_post_category');
            $postTable = $this->resource->getTableName('panth_blog_post');
            $offset = max(0, ($page - 1) * $pageSize);
            $select = $conn->select()
                ->from(['p' => $postTable], ['post_id'])
                ->join(['l' => $linkTable], 'l.post_id = p.post_id', [])
                ->where('l.category_id = ?', (int) $cat->getCategoryId())
                ->where('p.status = ?', PostInterface::STATUS_PUBLISHED)
                ->order('p.published_at DESC')
                ->order('p.post_id DESC')
                ->limit($pageSize, $offset);
            $this->storeVisibility->filterPosts($select, 'p.post_id');
            $ids = $conn->fetchCol($select);
            return array_map('intval', is_array($ids) ? $ids : []);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] CategoryView::fetchPostIds failed: ' . $e->getMessage());
            return [];
        }
    }
}
