<?php
declare(strict_types=1);

namespace Panth\Blog\Observer;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Url\PostUrlBuilder;
use Psr\Log\LoggerInterface;

class PostDeleteAfter implements ObserverInterface
{
    private const TABLE_INDEXNOW = 'panth_blog_indexnow_queue';

    public function __construct(
        private readonly PostUrlBuilder $postUrlBuilder,
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

            if (!$this->config->isIndexNowEnabled() || !$this->config->isNotifyOnDelete()) {
                return;
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
            $this->logger->warning('[PanthBlog PostDeleteAfter:indexnow] ' . $e->getMessage());
        }
    }
}
