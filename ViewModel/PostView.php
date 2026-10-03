<?php
declare(strict_types=1);

namespace Panth\Blog\ViewModel;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Api\AuthorRepositoryInterface;
use Panth\Blog\Api\CategoryRepositoryInterface;
use Panth\Blog\Api\Data\AuthorInterface;
use Panth\Blog\Api\Data\CategoryInterface;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Api\TagRepositoryInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Reader\ReadingTimeCalculator;
use Panth\Blog\Model\ResourceModel\Post as PostResource;
use Panth\Blog\Model\Schema\JsonLdRenderer;
use Panth\Blog\Model\Url\AuthorUrlBuilder;
use Panth\Blog\Model\Url\CategoryUrlBuilder;
use Panth\Blog\Model\Url\PostUrlBuilder;
use Panth\Blog\Model\Url\TagUrlBuilder;
use Psr\Log\LoggerInterface;
use Panth\Blog\Model\StoreVisibility;

class PostView implements ArgumentInterface
{
    private const REGISTRY_KEY = 'current_panth_blog_post';

    private bool $postLoaded = false;
    private ?PostInterface $post = null;
    private bool $authorLoaded = false;
    private ?AuthorInterface $author = null;
    private bool $primaryCategoryLoaded = false;
    private ?CategoryInterface $primaryCategory = null;

    private ?array $categories = null;

    private ?array $tags = null;

    private ?array $relatedPosts = null;
    private ?int $wordCount = null;
    private ?int $readingTimeMin = null;

    private ?array $tocItems = null;
    private ?string $processedContent = null;

    public function __construct(
        private readonly Registry $registry,
        private readonly Config $config,
        private readonly PostRepositoryInterface $postRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly TagRepositoryInterface $tagRepository,
        private readonly AuthorRepositoryInterface $authorRepository,
        private readonly PostResource $postResource,
        private readonly PostUrlBuilder $postUrl,
        private readonly CategoryUrlBuilder $categoryUrl,
        private readonly TagUrlBuilder $tagUrl,
        private readonly AuthorUrlBuilder $authorUrl,
        private readonly JsonLdRenderer $jsonLd,
        private readonly ReadingTimeCalculator $readingTime,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
        private readonly StoreVisibility $storeVisibility
    ) {
    }

    public function getPost(): ?PostInterface
    {
        if ($this->postLoaded) {
            return $this->post;
        }
        $this->postLoaded = true;
        $candidate = $this->registry->registry(self::REGISTRY_KEY);
        $this->post = $candidate instanceof PostInterface ? $candidate : null;
        return $this->post;
    }

    public function getAuthor(): ?AuthorInterface
    {
        if ($this->authorLoaded) {
            return $this->author;
        }
        $this->authorLoaded = true;
        $post = $this->getPost();
        if ($post === null) {
            return null;
        }
        $authorId = (int) ($post->getAuthorId() ?? 0);
        if ($authorId <= 0) {
            return null;
        }
        try {
            $this->author = $this->authorRepository->getById($authorId);
        } catch (NoSuchEntityException) {
            $this->author = null;
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] PostView::getAuthor failed: ' . $e->getMessage());
            $this->author = null;
        }
        return $this->author;
    }

    public function getPrimaryCategory(): ?CategoryInterface
    {
        if ($this->primaryCategoryLoaded) {
            return $this->primaryCategory;
        }
        $this->primaryCategoryLoaded = true;
        $post = $this->getPost();
        if ($post === null || $post->getPostId() === null) {
            return null;
        }
        try {
            $primaryId = $this->postResource->getPrimaryCategoryId((int) $post->getPostId());
            if ($primaryId === null || $primaryId <= 0) {
                return null;
            }
            $primary = $this->categoryRepository->getById($primaryId);
            $this->primaryCategory = $this->isCategoryShown($primary) ? $primary : null;
        } catch (NoSuchEntityException) {
            $this->primaryCategory = null;
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] PostView::getPrimaryCategory failed: ' . $e->getMessage());
            $this->primaryCategory = null;
        }
        return $this->primaryCategory;
    }

    public function getCategories(): array
    {
        if ($this->categories !== null) {
            return $this->categories;
        }
        $this->categories = [];
        $post = $this->getPost();
        if ($post === null || $post->getPostId() === null) {
            return $this->categories;
        }
        try {
            $ids = $this->postResource->getCategoryIds((int) $post->getPostId());
            foreach ($ids as $id) {
                try {
                    $category = $this->categoryRepository->getById((int) $id);
                } catch (NoSuchEntityException) {
                    continue;
                }
                if ($this->isCategoryShown($category)) {
                    $this->categories[] = $category;
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] PostView::getCategories failed: ' . $e->getMessage());
        }
        return $this->categories;
    }

    private function isCategoryShown(CategoryInterface $category): bool
    {
        return (int) $category->getIsActive() === 1
            && $this->storeVisibility->isCategoryVisible((int) $category->getCategoryId());
    }

    public function getTags(): array
    {
        if ($this->tags !== null) {
            return $this->tags;
        }
        $this->tags = [];
        $post = $this->getPost();
        if ($post === null || $post->getPostId() === null) {
            return $this->tags;
        }
        try {
            $ids = $this->postResource->getTagIds((int) $post->getPostId());
            foreach ($ids as $id) {
                try {
                    $this->tags[] = $this->tagRepository->getById((int) $id);
                } catch (NoSuchEntityException) {
                    continue;
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] PostView::getTags failed: ' . $e->getMessage());
        }
        return $this->tags;
    }

    public function getRelatedPosts(): array
    {
        if ($this->relatedPosts !== null) {
            return $this->relatedPosts;
        }
        $this->relatedPosts = [];
        $post = $this->getPost();
        if ($post === null || $post->getPostId() === null) {
            return $this->relatedPosts;
        }

        $postId = (int) $post->getPostId();
        $limit = max(0, $this->config->getRelatedPostsCount());
        if ($limit === 0) {
            return $this->relatedPosts;
        }

        $collected = [];
        try {
            $manualIds = $this->postResource->getRelatedIds($postId);
            foreach ($manualIds as $rid) {
                if (count($collected) >= $limit) {
                    break;
                }
                if ($rid === $postId) {
                    continue;
                }
                try {
                    $related = $this->postRepository->getById((int) $rid);
                } catch (NoSuchEntityException) {
                    continue;
                }
                if ($related->getStatus() !== PostInterface::STATUS_PUBLISHED
                    || !$this->storeVisibility->isPostVisible((int) $rid)
                ) {
                    continue;
                }
                $collected[$rid] = $related;
            }

            if (count($collected) < $limit) {
                $needed = $limit - count($collected);
                foreach ($this->fetchAutoRelatedIds($postId, $needed, array_keys($collected)) as $autoId) {
                    if (count($collected) >= $limit) {
                        break;
                    }
                    try {
                        $collected[$autoId] = $this->postRepository->getById((int) $autoId);
                    } catch (NoSuchEntityException) {
                        continue;
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] PostView::getRelatedPosts failed: ' . $e->getMessage());
        }

        $this->relatedPosts = array_values($collected);
        return $this->relatedPosts;
    }

    public function getCanonicalUrl(): string
    {
        $post = $this->getPost();
        if ($post === null) {
            return '';
        }
        $override = (string) ($post->getCanonicalUrl() ?? '');
        if ($override !== '') {
            return $override;
        }
        try {
            return $this->postUrl->getPostUrl($post);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] PostView::getCanonicalUrl failed: ' . $e->getMessage());
            return '';
        }
    }

    public function getDocumentTitle(): string
    {
        $post = $this->getPost();
        if ($post === null) {
            return '';
        }
        $postTitle = (string) ($post->getTitle() ?? '');
        $metaTitle = trim((string) ($post->getMetaTitle() ?? ''));
        $template = trim((string) $this->config->getTitleTemplate());
        if ($template === '' || strpos($template, '{{') === false) {
            return $metaTitle !== '' ? $metaTitle : $postTitle;
        }
        $category = $this->getPrimaryCategory();
        $author = $this->getAuthor();
        $tokens = [
            '{{post.title}}'      => $postTitle,
            '{{post.meta_title}}' => $metaTitle !== '' ? $metaTitle : $postTitle,
            '{{post.category}}'   => $category !== null ? (string) ($category->getName() ?? '') : '',
            '{{post.author}}'     => $author !== null ? (string) ($author->getDisplayName() ?? '') : '',
        ];
        $rendered = strtr($template, $tokens);
        $rendered = preg_replace('/\s*([|\-–—])\s*\1\s*/u', ' $1 ', (string) $rendered) ?? $rendered;
        $rendered = trim(preg_replace('/\s{2,}/u', ' ', (string) $rendered) ?? $rendered);
        $rendered = trim($rendered, " \t|-–—");
        return $rendered !== '' ? $rendered : ($metaTitle !== '' ? $metaTitle : $postTitle);
    }

    public function getTldr(): string
    {
        $post = $this->getPost();
        if ($post === null) {
            return '';
        }
        return trim((string) ($post->getTldrSummary() ?? ''));
    }

    public function getMetaDescription(): string
    {
        $post = $this->getPost();
        if ($post === null) {
            return (string) $this->config->getDefaultMetaDescription();
        }
        $meta = trim((string) ($post->getMetaDescription() ?? ''));
        if ($meta !== '') {
            return $meta;
        }
        $short = trim(strip_tags((string) ($post->getShortDescription() ?? '')));
        if ($short !== '') {
            if (function_exists('mb_substr') && mb_strlen($short) > 155) {
                return mb_substr($short, 0, 155);
            }
            return substr($short, 0, 155);
        }
        return (string) $this->config->getDefaultMetaDescription();
    }

    public function getOgImage(): ?string
    {
        $post = $this->getPost();
        if ($post === null) {
            return null;
        }
        $og = (string) ($post->getOgImage() ?? '');
        if ($og !== '') {
            return $this->getMediaUrl($og);
        }
        $featured = (string) ($post->getFeaturedImage() ?? '');
        if ($featured !== '') {
            return $this->getMediaUrl($featured);
        }
        return null;
    }

    public function getPostUrl(PostInterface $post): string
    {
        try {
            return $this->postUrl->getPostUrl($post);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] PostView::getPostUrl failed: ' . $e->getMessage());
            return '';
        }
    }

    public function getCategoryUrl(CategoryInterface $cat): string
    {
        try {
            return $this->categoryUrl->getCategoryUrl($cat);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] PostView::getCategoryUrl failed: ' . $e->getMessage());
            return '';
        }
    }

    public function getTagUrl(\Panth\Blog\Api\Data\TagInterface $tag): string
    {
        try {
            return $this->tagUrl->getTagUrl($tag);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] PostView::getTagUrl failed: ' . $e->getMessage());
            return '';
        }
    }

    public function getAuthorUrl(AuthorInterface $author): string
    {
        try {
            return $this->authorUrl->getAuthorUrl($author);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] PostView::getAuthorUrl failed: ' . $e->getMessage());
            return '';
        }
    }

    public function getMediaUrl(string $relative): string
    {
        if ($relative === '') {
            return '';
        }
        if (stripos($relative, 'http://') === 0 || stripos($relative, 'https://') === 0) {
            return $relative;
        }
        try {
            $base = (string) $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
            return rtrim($base, '/') . '/blog/' . ltrim($relative, '/');
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] PostView::getMediaUrl failed: ' . $e->getMessage());
            return $relative;
        }
    }

    public function getReadingTime(): int
    {
        if ($this->readingTimeMin !== null) {
            return $this->readingTimeMin;
        }
        $post = $this->getPost();
        if ($post === null) {
            return $this->readingTimeMin = 1;
        }
        $stored = $post->getReadingTimeMin();
        if ($stored !== null && $stored > 0) {
            return $this->readingTimeMin = (int) $stored;
        }
        try {
            $result = $this->readingTime->calculate(
                (string) ($post->getContent() ?? ''),
                $this->config->getReadingSpeedWpm()
            );
            $this->wordCount = (int) ($result['word_count'] ?? 0);
            return $this->readingTimeMin = max(1, (int) ($result['reading_time_min'] ?? 1));
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] PostView::getReadingTime failed: ' . $e->getMessage());
            return $this->readingTimeMin = 1;
        }
    }

    public function getWordCount(): int
    {
        if ($this->wordCount !== null) {
            return $this->wordCount;
        }
        $post = $this->getPost();
        if ($post === null) {
            return $this->wordCount = 0;
        }
        $stored = $post->getWordCount();
        if ($stored !== null && $stored > 0) {
            return $this->wordCount = (int) $stored;
        }
        try {
            $result = $this->readingTime->calculate(
                (string) ($post->getContent() ?? ''),
                $this->config->getReadingSpeedWpm()
            );
            $this->readingTimeMin = max(1, (int) ($result['reading_time_min'] ?? 1));
            return $this->wordCount = (int) ($result['word_count'] ?? 0);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] PostView::getWordCount failed: ' . $e->getMessage());
            return $this->wordCount = 0;
        }
    }

    public function getJsonLd(): string
    {
        $post = $this->getPost();
        if ($post === null) {
            return '';
        }
        try {
            $baseUrl = (string) $this->storeManager->getStore()->getBaseUrl();
            $breadcrumbs = [
                ['name' => 'Home', 'item' => $baseUrl],
                ['name' => 'Blog', 'item' => rtrim($baseUrl, '/') . '/' . $this->config->getRouteFrontName()],
            ];
            $primary = $this->getPrimaryCategory();
            if ($primary !== null) {
                $breadcrumbs[] = [
                    'name' => $primary->getName(),
                    'item' => $this->categoryUrl->getCategoryUrl($primary),
                ];
            }
            $breadcrumbs[] = [
                'name' => $post->getTitle(),
                'item' => $this->getCanonicalUrl(),
            ];
            return $this->jsonLd->renderForPost(
                $post,
                $this->getAuthor(),
                $primary,
                $this->getTags(),
                $breadcrumbs
            );
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] PostView::getJsonLd failed: ' . $e->getMessage());
            return '';
        }
    }

    public function getCitations(): array
    {
        $post = $this->getPost();
        if ($post === null) {
            return [];
        }
        $raw = (string) ($post->getCitationList() ?? '');
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? $row['title'] ?? ''));
            $url   = trim((string) ($row['url'] ?? ''));
            $type  = trim((string) ($row['type'] ?? 'WebPage'));
            if ($label === '' && $url === '') {
                continue;
            }
            $out[] = [
                'label' => $label !== '' ? $label : $url,
                'url'   => $url,
                'type'  => $type !== '' ? $type : 'WebPage',
            ];
        }
        return $out;
    }

    public function isShowReadingTime(): bool
    {
        try {
            return $this->config->isShowReadingTime();
        } catch (\Throwable) {
            return true;
        }
    }

    public function isShowAuthorBio(): bool
    {
        try {
            return $this->config->isShowAuthorBio();
        } catch (\Throwable) {
            return true;
        }
    }

    public function isShowShareButtons(): bool
    {
        try {
            return $this->config->isShowShareButtons();
        } catch (\Throwable) {
            return true;
        }
    }

    public function isTocEnabled(): bool
    {
        $post = $this->getPost();
        if ($post === null) {
            return false;
        }
        if (!$post->getEnableToc()) {
            return false;
        }
        if (!$this->config->isShowToc()) {
            return false;
        }
        $minH2 = $this->config->getTocMinH2();
        $content = (string) ($post->getContent() ?? '');
        $count = preg_match_all('/<h2\b[^>]*>/i', $content);
        return ($count !== false ? (int) $count : 0) >= $minH2;
    }

    public function extractToc(): array
    {
        if ($this->tocItems !== null) {
            return $this->tocItems;
        }
        $this->tocItems = [];
        $post = $this->getPost();
        if ($post === null) {
            return $this->tocItems;
        }
        $content = (string) ($post->getContent() ?? '');
        if ($content === '') {
            return $this->tocItems;
        }
        if (!preg_match_all(
            '/<(h2|h3)([^>]*)>(.+?)<\/\1>/is',
            $content,
            $matches,
            PREG_SET_ORDER
        )) {
            return $this->tocItems;
        }
        $usedAnchors = $this->collectExplicitIds($content);
        foreach ($matches as $m) {
            $level = strtolower($m[1]) === 'h3' ? 3 : 2;
            $attrs = (string) $m[2];
            $inner = trim(strip_tags((string) $m[3]));
            if ($inner === '') {
                continue;
            }
            $anchor = '';
            if (preg_match('/\bid\s*=\s*"([^"]+)"/i', $attrs, $idMatch)
                || preg_match('/\bid\s*=\s*\'([^\']+)\'/i', $attrs, $idMatch)
            ) {
                $anchor = trim($idMatch[1]);
            }
            if ($anchor === '') {
                $anchor = $this->slugifyInline($inner);
                if ($anchor === '') {
                    continue;
                }
                $base = $anchor;
                $i = 2;
                while (isset($usedAnchors[$anchor])) {
                    $anchor = $base . '-' . $i++;
                }
                $usedAnchors[$anchor] = true;
            }
            $this->tocItems[] = [
                'anchor' => $anchor,
                'text'   => $inner,
                'level'  => $level,
            ];
        }
        return $this->tocItems;
    }

    public function getProcessedContent(): string
    {
        if ($this->processedContent !== null) {
            return $this->processedContent;
        }
        $post = $this->getPost();
        $content = $post !== null ? (string) ($post->getContent() ?? '') : '';
        if ($content === '') {
            return $this->processedContent = '';
        }
        $used = $this->collectExplicitIds($content);
        $processed = preg_replace_callback(
            '/<(h2|h3)([^>]*)>(.+?)<\/\1>/is',
            function (array $m) use (&$used): string {
                $tag = strtolower($m[1]);
                $attrs = (string) $m[2];
                $inner = (string) $m[3];
                if (preg_match('/\bid\s*=\s*("[^"]*"|\'[^\']*\')/i', $attrs)) {
                    return '<' . $tag . $attrs . '>' . $inner . '</' . $tag . '>';
                }
                $text = trim(strip_tags($inner));
                $slug = $this->slugifyInline($text);
                if ($slug === '') {
                    return $m[0];
                }
                $base = $slug;
                $i = 2;
                while (isset($used[$slug])) {
                    $slug = $base . '-' . $i++;
                }
                $used[$slug] = true;
                $attrs = rtrim($attrs);
                return '<' . $tag . $attrs . ' id="' . $slug . '">' . $inner . '</' . $tag . '>';
            },
            $content
        );
        return $this->processedContent = is_string($processed) ? $processed : $content;
    }

    public function getShareLinks(): array
    {
        $post = $this->getPost();
        if ($post === null) {
            return [];
        }
        $canonical = $this->getCanonicalUrl();
        $title = (string) $post->getTitle();
        $encodedUrl   = rawurlencode($canonical);
        $encodedTitle = rawurlencode($title);
        return [
            [
                'network' => 'twitter',
                'url'     => 'https://twitter.com/intent/tweet?url=' . $encodedUrl . '&text=' . $encodedTitle,
            ],
            [
                'network' => 'linkedin',
                'url'     => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $encodedUrl,
            ],
            [
                'network' => 'copy',
                'url'     => $canonical,
            ],
        ];
    }

    private function fetchAutoRelatedIds(int $postId, int $limit, array $exclude): array
    {
        if ($limit <= 0) {
            return [];
        }
        try {
            $primary = $this->getPrimaryCategory();
            if ($primary === null || $primary->getCategoryId() === null) {
                return [];
            }
            $connection = $this->postResource->getConnection();
            $linkTable = $this->postResource->getTable(PostResource::TABLE_POST_CATEGORY);
            $postTable = $this->postResource->getMainTable();
            $excludeIds = array_merge([$postId], array_map('intval', $exclude));
            $select = $connection->select()
                ->from(['p' => $postTable], ['post_id'])
                ->join(
                    ['l' => $linkTable],
                    'l.post_id = p.post_id',
                    []
                )
                ->where('l.category_id = ?', (int) $primary->getCategoryId())
                ->where('p.status = ?', PostInterface::STATUS_PUBLISHED)
                ->where('p.post_id NOT IN (?)', $excludeIds)
                ->order('p.published_at DESC')
                ->limit($limit);
            $this->storeVisibility->filterPosts($select, 'p.post_id');
            $ids = $connection->fetchCol($select);
            return array_map('intval', is_array($ids) ? $ids : []);
        } catch (\Throwable $e) {
            $this->logger->info('[Panth_Blog] fetchAutoRelatedIds failed: ' . $e->getMessage());
            return [];
        }
    }

    private function collectExplicitIds(string $content): array
    {
        $ids = [];
        if (preg_match_all('/(?<![\w-])id\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $content, $found, PREG_SET_ORDER)) {
            foreach ($found as $match) {
                $id = trim((string) ($match[2] ?? '') !== '' ? $match[2] : $match[1]);
                if ($id !== '') {
                    $ids[$id] = true;
                }
            }
        }
        return $ids;
    }

    private function slugifyInline(string $text): string
    {
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/u', '-', $text) ?? '';
        $text = trim($text, '-');
        if (function_exists('mb_substr') && mb_strlen($text) > 80) {
            $text = mb_substr($text, 0, 80);
            $text = trim($text, '-');
        } elseif (strlen($text) > 80) {
            $text = substr($text, 0, 80);
            $text = trim($text, '-');
        }
        return $text;
    }
}
