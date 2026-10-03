<?php
declare(strict_types=1);

namespace Panth\Blog\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Panth\Blog\Api\Data\CategoryInterface;
use Panth\Blog\Model\Url\UrlHistoryManager;
use Psr\Log\LoggerInterface;

class CategorySaveAfter implements ObserverInterface
{
    public function __construct(
        private readonly UrlHistoryManager $urlHistoryManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        try {
            $category = $observer->getEvent()->getData('category');
            if (!$category instanceof CategoryInterface) {
                return;
            }

            $oldKey = '';
            if (method_exists($category, 'getOrigData')) {
                $oldKey = (string) ($category->getOrigData(CategoryInterface::URL_KEY) ?? '');
            }
            $newKey = (string) $category->getUrlKey();
            if ($oldKey === '' || $newKey === '' || $oldKey === $newKey) {
                return;
            }

            $this->urlHistoryManager->recordSlugChange(
                'category',
                (int) ($category->getCategoryId() ?? 0),
                $oldKey,
                $newKey
            );
        } catch (\Throwable $e) {
            $this->logger->warning('[PanthBlog CategorySaveAfter] ' . $e->getMessage());
        }
    }
}
