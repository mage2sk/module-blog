<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Cache;

use Magento\Framework\Event\ManagerInterface;
use Psr\Log\LoggerInterface;

class PageCacheCleaner
{
    public function __construct(
        private readonly ManagerInterface $eventManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function clean(array $tags): void
    {
        $tags = array_values(array_unique(array_filter(array_map('strval', $tags))));
        if ($tags === []) {
            return;
        }
        try {
            $this->eventManager->dispatch('clean_cache_by_tags', ['object' => new TagSet($tags)]);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] PageCacheCleaner failed: ' . $e->getMessage());
        }
    }
}
