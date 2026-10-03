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
use Panth\Blog\Api\Data\TagInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Api\TagRepositoryInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Schema\JsonLdRenderer;
use Panth\Blog\Model\Url\PostUrlBuilder;
use Panth\Blog\Model\Url\TagUrlBuilder;
use Psr\Log\LoggerInterface;
use Panth\Blog\Model\StoreVisibility;

class TagView implements ArgumentInterface
{
    private const REGISTRY_KEY = 'current_panth_blog_tag';

    private bool $tagLoaded = false;
    private ?TagInterface $tag = null;

    private array $pagesCache = [];
    private ?int $totalPosts = null;

    public function __construct(
        private readonly Registry $registry,
        private readonly Config $config,
        private readonly TagRepositoryInterface $tagRepository,
        private readonly PostRepositoryInterface $postRepository,
        private readonly ResourceConnection $resource,
        private readonly RequestInterface $request,
        private readonly StoreManagerInterface $storeManager,
        private readonly JsonLdRenderer $jsonLd,
        private readonly TagUrlBuilder $tagUrl,
        private readonly PostUrlBuilder $postUrl,
        private readonly LoggerInterface $logger,
        private readonly StoreVisibility $storeVisibility
    ) {
    }

    public function getTag(): ?TagInterface
    {
        if ($this->tagLoaded) {
            return $this->tag;
        }
        $this->tagLoaded = true;
        $candidate = $this->registry->registry(self::REGISTRY_KEY);
        $this->tag = $candidate instanceof TagInterface ? $candidate : null;
        return $this->tag;
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
        foreach ($this->fetchPostIds($page, $pageSize) as $id) {
            try {
                $posts[] = $this->postRepository->getById((int) $id);
            } catch (\Throwable $e) {
                $this->logger->info('[Panth_Blog] TagView::getPosts skip ' . $id . ': ' . $e->getMessage());
            }
        }
        return $this->pagesCache[$cacheKey] = $posts;
    }

    public function getPostUrl(PostInterface $post): string
    {
        try {
            return $this->postUrl->getPostUrl($post);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] TagView::getPostUrl failed: ' . $e->getMessage());
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
            $this->logger->warning('[Panth_Blog] TagView::getMediaUrl failed: ' . $e->getMessage());
            return $path;
        }
    }

    public function getPageUrl(int $page): string
    {
        $tag = $this->getTag();
        if ($tag === null) {
            return '';
        }
        try {
            return $this->tagUrl->getPaginatedTagUrl($tag, $page);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] TagView::getPageUrl failed: ' . $e->getMessage());
            return '';
        }
    }

    public function getTagUrl(TagInterface $tag): string
    {
        try {
            return $this->tagUrl->getTagUrl($tag);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] TagView::getTagUrl failed: ' . $e->getMessage());
            return '';
        }
    }

    public function getTotalPosts(): int
    {
        if ($this->totalPosts !== null) {
            return $this->totalPosts;
        }
        $tag = $this->getTag();
        if ($tag === null || $tag->getTagId() === null) {
            return $this->totalPosts = 0;
        }
        try {
            $conn = $this->resource->getConnection();
            $linkTable = $this->resource->getTableName('panth_blog_post_tag');
            $postTable = $this->resource->getTableName('panth_blog_post');
            $select = $conn->select()
                ->from(['p' => $postTable], ['cnt' => new \Zend_Db_Expr('COUNT(DISTINCT p.post_id)')])
                ->join(['l' => $linkTable], 'l.post_id = p.post_id', [])
                ->where('l.tag_id = ?', (int) $tag->getTagId())
                ->where('p.status = ?', PostInterface::STATUS_PUBLISHED);
            $this->storeVisibility->filterPosts($select, 'p.post_id');
            return $this->totalPosts = (int) $conn->fetchOne($select);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] TagView::getTotalPosts failed: ' . $e->getMessage());
            return $this->totalPosts = 0;
        }
    }

    public function getCanonicalUrl(): string
    {
        $tag = $this->getTag();
        if ($tag === null) {
            return '';
        }
        try {
            return $this->tagUrl->getTagUrl($tag);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] TagView::getCanonicalUrl failed: ' . $e->getMessage());
            return '';
        }
    }

    public function getJsonLd(): string
    {
        $tag = $this->getTag();
        if ($tag === null) {
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
            $url = $this->tagUrl->getTagUrl($tag);
            $baseUrl = (string) $this->storeManager->getStore()->getBaseUrl();
            $breadcrumbs = [
                ['name' => 'Home', 'item' => $baseUrl],
                ['name' => 'Blog', 'item' => rtrim($baseUrl, '/') . '/' . $this->config->getRouteFrontName()],
                ['name' => $tag->getName(), 'item' => $url],
            ];
            return $this->jsonLd->renderForTag(
                (string) $tag->getName(),
                $url,
                (string) ($tag->getDescription() ?? ''),
                $items,
                $this->getTotalPosts(),
                $breadcrumbs
            );
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] TagView::getJsonLd failed: ' . $e->getMessage());
            return '';
        }
    }

    private function fetchPostIds(int $page, int $pageSize): array
    {
        $tag = $this->getTag();
        if ($tag === null || $tag->getTagId() === null) {
            return [];
        }
        try {
            $conn = $this->resource->getConnection();
            $linkTable = $this->resource->getTableName('panth_blog_post_tag');
            $postTable = $this->resource->getTableName('panth_blog_post');
            $offset = max(0, ($page - 1) * $pageSize);
            $select = $conn->select()
                ->from(['p' => $postTable], ['post_id'])
                ->join(['l' => $linkTable], 'l.post_id = p.post_id', [])
                ->where('l.tag_id = ?', (int) $tag->getTagId())
                ->where('p.status = ?', PostInterface::STATUS_PUBLISHED)
                ->order('p.published_at DESC')
                ->order('p.post_id DESC')
                ->limit($pageSize, $offset);
            $this->storeVisibility->filterPosts($select, 'p.post_id');
            $ids = $conn->fetchCol($select);
            return array_map('intval', is_array($ids) ? $ids : []);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] TagView::fetchPostIds failed: ' . $e->getMessage());
            return [];
        }
    }
}
