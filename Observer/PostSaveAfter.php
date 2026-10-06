<?php
declare(strict_types=1);

namespace Panth\Blog\Observer;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Cache\Type\JsonLd as JsonLdCacheType;
use Panth\Blog\Model\IndexNow\PostStoreUrls;
use Panth\Blog\Model\Url\UrlHistoryManager;
use Psr\Log\LoggerInterface;

class PostSaveAfter implements ObserverInterface
{
    private const TABLE_INDEXNOW = 'panth_blog_indexnow_queue';
    private const TABLE_CROSSLINK_RULE = 'panth_crosslinks_rule';
    private const TRACKED_FIELDS = [
        'title', 'url_key', 'content', 'short_description', 'meta_title', 'meta_description',
        'meta_keywords', 'meta_robots', 'canonical_url', 'featured_image', 'featured_image_alt',
        'og_image', 'status', 'published_at', 'tldr_summary', 'citation_list', 'author_id',
        'is_featured', 'enable_toc', 'enable_comments', 'layout_template',
    ];
    private const CROSSLINKS_REPO_CLASS = 'Panth\\Crosslinks\\Api\\RuleRepositoryInterface';

    public function __construct(
        private readonly PostStoreUrls $postStoreUrls,
        private readonly UrlHistoryManager $urlHistoryManager,
        private readonly ResourceConnection $resource,
        private readonly Config $config,
        private readonly TypeListInterface $cacheTypeList,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        try {
            $post = $observer->getEvent()->getData('post');
            if (!$post instanceof PostInterface) {
                return;
            }

            $contentChanged = $this->hasContentChanges($post);
            $currentStores = $this->postStoreUrls->getAssignedStoreIds((int) ($post->getPostId() ?? 0));
            $pending = method_exists($post, 'getData') ? $post->getData('store_ids') : null;
            $targetStores = $pending !== null ? $this->postStoreUrls->normalize($pending) : $currentStores;
            if (!$contentChanged && $targetStores === $currentStores) {
                return;
            }

            $this->maybeQueueIndexNow($post, $contentChanged, $currentStores, $targetStores);
            if (!$contentChanged) {
                return;
            }
            $this->maybeRecordUrlHistory($post);
            $this->maybeSeedCrosslinks($post);
            $this->invalidateJsonLdCache($post);
        } catch (\Throwable $e) {
            $this->logger->warning('[PanthBlog PostSaveAfter] ' . $e->getMessage());
        }
    }

    private function maybeQueueIndexNow(
        PostInterface $post,
        bool $contentChanged,
        array $currentStores,
        array $targetStores
    ): void {
        try {
            if (!$this->config->isIndexNowEnabled()) {
                return;
            }
            $newStatus = (string) $post->getStatus();
            $oldStatus = (string) ($this->resolveOrigData($post, PostInterface::STATUS) ?? '');
            $wasPublished = $oldStatus === PostInterface::STATUS_PUBLISHED;
            $isPublished = $newStatus === PostInterface::STATUS_PUBLISHED;
            $urlKey = (string) $post->getUrlKey();
            $oldKey = (string) ($this->resolveOrigData($post, PostInterface::URL_KEY) ?? '');
            $oldKey = $oldKey !== '' ? $oldKey : $urlKey;

            $currentUrls = $wasPublished ? $this->postStoreUrls->getUrls($currentStores, $oldKey) : [];
            $targetUrls = $isPublished ? $this->postStoreUrls->getUrls($targetStores, $urlKey) : [];

            $rows = [];
            if ($isPublished && $this->config->isNotifyOnPublish()) {
                $notifyAll = !$wasPublished || ($contentChanged && $this->config->isNotifyOnUpdate());
                foreach ($targetUrls as $storeId => $url) {
                    if ($notifyAll || !isset($currentUrls[$storeId])) {
                        $rows[$storeId . '|' . $url] = [$storeId, $url];
                    }
                }
            }
            if ($wasPublished && $this->config->isNotifyOnDelete()) {
                foreach ($currentUrls as $storeId => $url) {
                    if (!isset($targetUrls[$storeId])) {
                        $rows[$storeId . '|' . $url] = [$storeId, $url];
                    }
                }
            }
            if ($rows === []) {
                return;
            }

            $connection = $this->resource->getConnection();
            $table = $this->resource->getTableName(self::TABLE_INDEXNOW);
            if (!$connection->isTableExists($table)) {
                return;
            }
            foreach ($rows as [$storeId, $url]) {
                $connection->insert($table, [
                    'url'      => $url,
                    'store_id' => $storeId,
                    'status'   => 'pending',
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('[PanthBlog PostSaveAfter:indexnow] ' . $e->getMessage());
        }
    }

    private function maybeRecordUrlHistory(PostInterface $post): void
    {
        try {
            $oldKey = (string) ($this->resolveOrigData($post, PostInterface::URL_KEY) ?? '');
            $newKey = (string) $post->getUrlKey();
            if ($oldKey === '' || $newKey === '' || $oldKey === $newKey) {
                return;
            }

            $this->urlHistoryManager->recordSlugChange(
                'post',
                (int) ($post->getPostId() ?? 0),
                $oldKey,
                $newKey
            );
        } catch (\Throwable $e) {
            $this->logger->warning('[PanthBlog PostSaveAfter:urlhistory] ' . $e->getMessage());
        }
    }

    private function maybeSeedCrosslinks(PostInterface $post): void
    {
        try {
            if (!class_exists(self::CROSSLINKS_REPO_CLASS)) {
                return;
            }
            $connection = $this->resource->getConnection();
            $table = $this->resource->getTableName(self::TABLE_CROSSLINK_RULE);
            if (!$connection->isTableExists($table)) {
                return;
            }
        } catch (\Throwable $e) {
            $this->logger->warning('[PanthBlog PostSaveAfter:crosslinks] ' . $e->getMessage());
        }
    }

    private function invalidateJsonLdCache(PostInterface $post): void
    {
        try {
            $this->cacheTypeList->invalidate(JsonLdCacheType::TYPE_IDENTIFIER);
        } catch (\Throwable $e) {
            $this->logger->warning('[PanthBlog PostSaveAfter:cache] ' . $e->getMessage());
        }
    }

    private function hasContentChanges(PostInterface $post): bool
    {
        if (!method_exists($post, 'getOrigData') || !method_exists($post, 'getData')) {
            return true;
        }
        $orig = $post->getOrigData();
        if (!is_array($orig) || $orig === []) {
            return true;
        }
        foreach (self::TRACKED_FIELDS as $field) {
            $old = $orig[$field] ?? null;
            $new = $post->getData($field);
            if ($field === 'published_at') {
                $oldTime = $this->toTimestamp($old);
                $newTime = $this->toTimestamp($new);
                if ($oldTime !== $newTime) {
                    return true;
                }
                continue;
            }
            if (is_array($old) || is_array($new)) {
                if ($old != $new) {
                    return true;
                }
                continue;
            }
            if ((string) $old !== (string) $new) {
                return true;
            }
        }

        return false;
    }

    private function toTimestamp(mixed $value): ?int
    {
        if ($value === null || $value === '' || !is_scalar($value)) {
            return null;
        }
        try {
            return (new \DateTime((string) $value, new \DateTimeZone('UTC')))->getTimestamp();
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveOrigData(PostInterface $post, string $key): mixed
    {
        if (method_exists($post, 'getOrigData')) {
            return $post->getOrigData($key);
        }
        return null;
    }
}
