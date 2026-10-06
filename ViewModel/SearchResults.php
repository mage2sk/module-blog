<?php
declare(strict_types=1);

namespace Panth\Blog\ViewModel;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Url\PostUrlBuilder;
use Psr\Log\LoggerInterface;
use Panth\Blog\Model\StoreVisibility;

class SearchResults implements ArgumentInterface
{
    private const REGISTRY_KEY = 'panth_blog_search_query';

    private array $pagesCache = [];
    private ?int $totalResults = null;
    private ?string $query = null;

    public function __construct(
        private readonly Registry $registry,
        private readonly ResourceConnection $resource,
        private readonly Config $config,
        private readonly PostRepositoryInterface $postRepository,
        private readonly RequestInterface $request,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $urlBuilder,
        private readonly PostUrlBuilder $postUrl,
        private readonly LoggerInterface $logger,
        private readonly StoreVisibility $storeVisibility
    ) {
    }

    public function getQuery(): string
    {
        if ($this->query !== null) {
            return $this->query;
        }
        $registered = $this->registry->registry(self::REGISTRY_KEY);
        if (is_string($registered) && $registered !== '') {
            return $this->query = trim($registered);
        }
        $raw = $this->request->getParam('q');
        if (is_string($raw)) {
            return $this->query = trim($raw);
        }
        return $this->query = '';
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
        return $this->config->getPostsPerPage();
    }

    public function getResults(): array
    {
        $page = max(1, $this->getCurrentPage());
        $perPage = $this->getPostsPerPage();
        $cacheKey = $page . ':' . $perPage;
        if (isset($this->pagesCache[$cacheKey])) {
            return $this->pagesCache[$cacheKey];
        }
        $query = $this->getQuery();
        if ($query === '') {
            return $this->pagesCache[$cacheKey] = [];
        }
        $posts = [];
        try {
            $conn = $this->resource->getConnection();
            $postTable = $this->resource->getTableName('panth_blog_post');
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';
            $offset = max(0, ($page - 1) * $perPage);

            $relevance = new \Zend_Db_Expr(
                "(CASE WHEN title LIKE " . $conn->quote($like) . " THEN 3 ELSE 0 END) "
                . "+ (CASE WHEN short_description LIKE " . $conn->quote($like) . " THEN 2 ELSE 0 END) "
                . "+ (CASE WHEN content LIKE " . $conn->quote($like) . " THEN 1 ELSE 0 END)"
            );
            $quoted = $conn->quote($like);
            $select = $conn->select()
                ->from($postTable, ['post_id', 'score' => $relevance])
                ->where('status = ?', PostInterface::STATUS_PUBLISHED)
                ->where(
                    'title LIKE ' . $quoted
                    . ' OR short_description LIKE ' . $quoted
                    . ' OR content LIKE ' . $quoted
                )
                ->order('score DESC')
                ->order('published_at DESC')
                ->limit($perPage, $offset);
            $this->storeVisibility->filterPosts($select, $postTable . '.post_id');
            $rows = $conn->fetchAll($select);
            foreach ($rows as $row) {
                try {
                    $posts[] = $this->postRepository->getById((int) $row['post_id']);
                } catch (\Throwable $e) {
                    $this->logger->info('[Panth_Blog] SearchResults skip ' . ($row['post_id'] ?? '?') . ': ' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] SearchResults::getResults failed: ' . $e->getMessage());
        }
        return $this->pagesCache[$cacheKey] = $posts;
    }

    public function getTotalPosts(): int
    {
        return $this->getTotalResults();
    }

    public function getPostUrl(PostInterface $post): string
    {
        try {
            return $this->postUrl->getPostUrl($post);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] SearchResults::getPostUrl failed: ' . $e->getMessage());
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
            $this->logger->warning('[Panth_Blog] SearchResults::getMediaUrl failed: ' . $e->getMessage());
            return $path;
        }
    }

    public function getPageUrl(int $page): string
    {
        try {
            $params = ['_query' => ['q' => $this->getQuery()]];
            if ($page > 1) {
                $params['_query']['page'] = $page;
            }
            return $this->urlBuilder->getDirectUrl($this->config->getRouteFrontName() . '/search', $params);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] SearchResults::getPageUrl failed: ' . $e->getMessage());
            return '';
        }
    }

    public function getSearchActionUrl(): string
    {
        try {
            return $this->urlBuilder->getDirectUrl($this->config->getRouteFrontName() . '/search');
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] SearchResults::getSearchActionUrl failed: ' . $e->getMessage());
            return '/' . $this->config->getRouteFrontName() . '/search';
        }
    }

    public function getTotalResults(): int
    {
        if ($this->totalResults !== null) {
            return $this->totalResults;
        }
        $query = $this->getQuery();
        if ($query === '') {
            return $this->totalResults = 0;
        }
        try {
            $conn = $this->resource->getConnection();
            $postTable = $this->resource->getTableName('panth_blog_post');
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';
            $quoted = $conn->quote($like);
            $select = $conn->select()
                ->from($postTable, ['cnt' => new \Zend_Db_Expr('COUNT(post_id)')])
                ->where('status = ?', PostInterface::STATUS_PUBLISHED)
                ->where(
                    'title LIKE ' . $quoted
                    . ' OR short_description LIKE ' . $quoted
                    . ' OR content LIKE ' . $quoted
                );
            $this->storeVisibility->filterPosts($select, $postTable . '.post_id');
            return $this->totalResults = (int) $conn->fetchOne($select);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] SearchResults::getTotalResults failed: ' . $e->getMessage());
            return $this->totalResults = 0;
        }
    }
}
