<?php
declare(strict_types=1);

namespace Panth\Blog\Cron;

use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Feed\FeedRepository;
use Psr\Log\LoggerInterface;

class WarmFeedCache
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
        private readonly FeedRepository $feedRepository,
        private readonly Config $config
    ) {
    }

    public function execute(): void
    {
        try {
            $this->feedRepository->invalidate();

            $warmed = 0;
            foreach ($this->storeManager->getStores(false) as $store) {
                $storeId = (int) $store->getId();
                if (!$this->config->isEnabled($storeId) || !$this->config->isFeedEnabled($storeId)) {
                    continue;
                }
                try {
                    $this->feedRepository->buildSiteWideRss($storeId);
                    $this->feedRepository->buildSiteWideAtom($storeId);
                    $warmed++;
                } catch (\Throwable $inner) {
                    $this->logger->warning(sprintf(
                        '[PanthBlog WarmFeedCache] store %d failed: %s',
                        $storeId,
                        $inner->getMessage()
                    ));
                }
            }

            $this->logger->info(sprintf(
                '[PanthBlog WarmFeedCache] warmed %d store(s)',
                $warmed
            ));
        } catch (\Throwable $e) {
            $this->logger->warning('[PanthBlog WarmFeedCache] ' . $e->getMessage());
        }
    }
}
