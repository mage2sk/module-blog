<?php
declare(strict_types=1);

namespace Panth\Blog\Observer;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\IndexNow\PostStoreUrls;
use Panth\Blog\Model\Url\UrlHistoryManager;
use Psr\Log\LoggerInterface;

class PostDeleteAfter implements ObserverInterface
{
    private const TABLE_INDEXNOW = 'panth_blog_indexnow_queue';
    public const STASH_KEY = 'indexnow_store_ids';

    public function __construct(
        private readonly PostStoreUrls $postStoreUrls,
        private readonly UrlHistoryManager $urlHistoryManager,
        private readonly ResourceConnection $resource,
        private readonly Config $config,
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

            $this->urlHistoryManager->deleteForEntity('post', (int) ($post->getPostId() ?? 0));

            if (!$this->config->isIndexNowEnabled() || !$this->config->isNotifyOnDelete()) {
                return;
            }

            $connection = $this->resource->getConnection();
            $table = $this->resource->getTableName(self::TABLE_INDEXNOW);
            if (!$connection->isTableExists($table)) {
                return;
            }

            if ((string) $post->getStatus() !== PostInterface::STATUS_PUBLISHED) {
                return;
            }
            $stores = method_exists($post, 'getData') ? $post->getData(self::STASH_KEY) : null;
            $urls = $this->postStoreUrls->getUrls(is_array($stores) ? $stores : [0], (string) $post->getUrlKey());
            foreach ($urls as $storeId => $url) {
                $connection->insert($table, [
                    'url'      => $url,
                    'store_id' => $storeId,
                    'status'   => 'pending',
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('[PanthBlog PostDeleteAfter:indexnow] ' . $e->getMessage());
        }
    }
}
