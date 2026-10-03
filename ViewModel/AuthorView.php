<?php
declare(strict_types=1);

namespace Panth\Blog\ViewModel;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Api\AuthorRepositoryInterface;
use Panth\Blog\Api\Data\AuthorInterface;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Schema\JsonLdRenderer;
use Panth\Blog\Model\Url\AuthorUrlBuilder;
use Panth\Blog\Model\Url\PostUrlBuilder;
use Psr\Log\LoggerInterface;
use Panth\Blog\Model\StoreVisibility;

class AuthorView implements ArgumentInterface
{
    private const REGISTRY_KEY = 'current_panth_blog_author';

    private const ALLOWED_LINK_KEYS = ['linkedin', 'github', 'x', 'twitter', 'mastodon', 'upwork', 'youtube'];

    private bool $authorLoaded = false;
    private ?AuthorInterface $author = null;

    private array $pagesCache = [];
    private ?int $totalPosts = null;

    public function __construct(
        private readonly Registry $registry,
        private readonly Config $config,
        private readonly AuthorRepositoryInterface $authorRepository,
        private readonly PostRepositoryInterface $postRepository,
        private readonly ResourceConnection $resource,
        private readonly RequestInterface $request,
        private readonly JsonLdRenderer $jsonLd,
        private readonly AuthorUrlBuilder $authorUrl,
        private readonly PostUrlBuilder $postUrl,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
        private readonly StoreVisibility $storeVisibility
    ) {
    }

    public function getAuthor(): ?AuthorInterface
    {
        if ($this->authorLoaded) {
            return $this->author;
        }
        $this->authorLoaded = true;
        $candidate = $this->registry->registry(self::REGISTRY_KEY);
        $this->author = $candidate instanceof AuthorInterface ? $candidate : null;
        return $this->author;
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

    public function getPosts(): array
    {
        $page = max(1, $this->getCurrentPage());
        $pageSize = max(1, $this->getPostsPerPage());
        $cacheKey = $page . ':' . $pageSize;
        if (isset($this->pagesCache[$cacheKey])) {
            return $this->pagesCache[$cacheKey];
        }
        $posts = [];
        $ids = $this->fetchPostIds($page, $pageSize);
        foreach ($ids as $id) {
            try {
                $posts[] = $this->postRepository->getById((int) $id);
            } catch (\Throwable $e) {
                $this->logger->info('[Panth_Blog] AuthorView::getPosts skip ' . $id . ': ' . $e->getMessage());
            }
        }
        return $this->pagesCache[$cacheKey] = $posts;
    }

    public function getPostUrl(PostInterface $post): string
    {
        try {
            return $this->postUrl->getPostUrl($post);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] AuthorView::getPostUrl failed: ' . $e->getMessage());
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
            $this->logger->warning('[Panth_Blog] AuthorView::getMediaUrl failed: ' . $e->getMessage());
            return $path;
        }
    }

    public function getPageUrl(int $page): string
    {
        $author = $this->getAuthor();
        if ($author === null) {
            return '';
        }
        try {
            return $this->authorUrl->getPaginatedAuthorUrl($author, $page);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] AuthorView::getPageUrl failed: ' . $e->getMessage());
            return '';
        }
    }

    public function getAuthorUrl(AuthorInterface $author): string
    {
        try {
            return $this->authorUrl->getAuthorUrl($author);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] AuthorView::getAuthorUrl failed: ' . $e->getMessage());
            return '';
        }
    }

    public function getTotalPosts(): int
    {
        if ($this->totalPosts !== null) {
            return $this->totalPosts;
        }
        $author = $this->getAuthor();
        if ($author === null || $author->getAuthorId() === null) {
            return $this->totalPosts = 0;
        }
        try {
            $conn = $this->resource->getConnection();
            $postTable = $this->resource->getTableName('panth_blog_post');
            $select = $conn->select()
                ->from($postTable, ['cnt' => new \Zend_Db_Expr('COUNT(post_id)')])
                ->where('author_id = ?', (int) $author->getAuthorId())
                ->where('status = ?', PostInterface::STATUS_PUBLISHED);
            $this->storeVisibility->filterPosts($select, $postTable . '.post_id');
            return $this->totalPosts = (int) $conn->fetchOne($select);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] AuthorView::getTotalPosts failed: ' . $e->getMessage());
            return $this->totalPosts = 0;
        }
    }

    public function getJsonLd(): string
    {
        $author = $this->getAuthor();
        if ($author === null) {
            return '';
        }
        try {
            $baseUrl = (string) $this->storeManager->getStore()->getBaseUrl();
            $authorUrl = '';
            try {
                $authorUrl = $this->authorUrl->getAuthorUrl($author);
            } catch (\Throwable) {
            }
            $breadcrumbs = [
                ['name' => 'Home', 'item' => rtrim($baseUrl, '/') . '/'],
                ['name' => 'Blog', 'item' => rtrim($baseUrl, '/') . '/' . $this->config->getRouteFrontName()],
                ['name' => (string) $author->getDisplayName(), 'item' => $authorUrl],
            ];
            return $this->jsonLd->renderForAuthor($author, $breadcrumbs);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] AuthorView::getJsonLd failed: ' . $e->getMessage());
            return '';
        }
    }

    public function getSocialLinks(): array
    {
        $author = $this->getAuthor();
        if ($author === null) {
            return [];
        }
        $raw = (string) ($author->getLinks() ?? '');
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $key => $url) {
            $k = strtolower((string) $key);
            if (!in_array($k, self::ALLOWED_LINK_KEYS, true)) {
                continue;
            }
            $u = trim((string) $url);
            if ($u === '') {
                continue;
            }
            $out[$k] = $u;
        }
        return $out;
    }

    private function fetchPostIds(int $page, int $pageSize): array
    {
        $author = $this->getAuthor();
        if ($author === null || $author->getAuthorId() === null) {
            return [];
        }
        try {
            $conn = $this->resource->getConnection();
            $postTable = $this->resource->getTableName('panth_blog_post');
            $offset = max(0, ($page - 1) * $pageSize);
            $select = $conn->select()
                ->from($postTable, ['post_id'])
                ->where('author_id = ?', (int) $author->getAuthorId())
                ->where('status = ?', PostInterface::STATUS_PUBLISHED)
                ->order('published_at DESC')
                ->order('post_id DESC')
                ->limit($pageSize, $offset);
            $this->storeVisibility->filterPosts($select, $postTable . '.post_id');
            $ids = $conn->fetchCol($select);
            return array_map('intval', is_array($ids) ? $ids : []);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] AuthorView::fetchPostIds failed: ' . $e->getMessage());
            return [];
        }
    }
}
