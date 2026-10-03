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
use Panth\Blog\Model\Url\PostUrlBuilder;
use Panth\Blog\Model\Url\UrlHistoryManager;
use Psr\Log\LoggerInterface;

class PostSaveAfter implements ObserverInterface
{
    private const TABLE_INDEXNOW = 'panth_blog_indexnow_queue';
    private const TABLE_CROSSLINK_RULE = 'panth_crosslinks_rule';
    private const CROSSLINKS_REPO_CLASS = 'Panth\\Crosslinks\\Api\\RuleRepositoryInterface';

    public function __construct(
        private readonly PostUrlBuilder $postUrlBuilder,
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

            $this->maybeQueueIndexNow($post);
            $this->maybeRecordUrlHistory($post);
            $this->maybeSeedCrosslinks($post);
            $this->invalidateJsonLdCache($post);
        } catch (\Throwable $e) {
            $this->logger->warning('[PanthBlog PostSaveAfter] ' . $e->getMessage());
        }
    }

    private function maybeQueueIndexNow(PostInterface $post): void
    {
        try {
            if (!$this->config->isIndexNowEnabled() || !$this->config->isNotifyOnPublish()) {
                return;
            }

            $newStatus = (string) $post->getStatus();
            if ($newStatus !== PostInterface::STATUS_PUBLISHED) {
                return;
            }

            $oldStatus = $this->resolveOrigData($post, PostInterface::STATUS);
            if ($oldStatus === PostInterface::STATUS_PUBLISHED) {
                if (!$this->config->isNotifyOnUpdate()) {
                    return;
                }
            }

            $connection = $this->resource->getConnection();
            $table = $this->resource->getTableName(self::TABLE_INDEXNOW);
            if (!$connection->isTableExists($table)) {
                return;
            }

            $url = $this->postUrlBuilder->getPostUrl($post);
            if ($url === '') {
                return;
            }

            $connection->insert($table, [
                'url'      => $url,
                'store_id' => 0,
                'status'   => 'pending',
            ]);
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

    private function resolveOrigData(PostInterface $post, string $key): mixed
    {
        if (method_exists($post, 'getOrigData')) {
            return $post->getOrigData($key);
        }
        return null;
    }
}
