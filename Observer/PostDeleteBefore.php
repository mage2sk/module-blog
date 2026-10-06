<?php
declare(strict_types=1);

namespace Panth\Blog\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Model\IndexNow\PostStoreUrls;
use Psr\Log\LoggerInterface;

class PostDeleteBefore implements ObserverInterface
{
    public function __construct(
        private readonly PostStoreUrls $postStoreUrls,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        try {
            $post = $observer->getEvent()->getData('post');
            if (!$post instanceof PostInterface || !method_exists($post, 'setData')) {
                return;
            }
            $post->setData(
                PostDeleteAfter::STASH_KEY,
                $this->postStoreUrls->getAssignedStoreIds((int) ($post->getPostId() ?? 0))
            );
        } catch (\Throwable $e) {
            $this->logger->warning('[PanthBlog PostDeleteBefore] ' . $e->getMessage());
        }
    }
}
