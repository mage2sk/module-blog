<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Panth\Blog\Model\ResourceModel\Category\CollectionFactory;

class Categories implements OptionSourceInterface
{
    public function __construct(
        private readonly CollectionFactory $categoryCollectionFactory
    ) {
    }

    public function toOptionArray(): array
    {
        try {
            $collection = $this->categoryCollectionFactory->create();
            $collection->addFieldToFilter('is_active', 1);
            $collection->setOrder('level', 'ASC');
            $collection->setOrder('name', 'ASC');

            $options = [];
            foreach ($collection as $category) {
                $level = (int) $category->getData('level');
                $indent = $level > 0 ? str_repeat('- ', $level) : '';
                $options[] = [
                    'value' => (int) $category->getCategoryId(),
                    'label' => $indent . (string) $category->getName(),
                ];
            }
            return $options;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
