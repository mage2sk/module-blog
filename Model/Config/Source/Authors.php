<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Panth\Blog\Model\ResourceModel\Author\CollectionFactory;

class Authors implements OptionSourceInterface
{
    public function __construct(
        private readonly CollectionFactory $authorCollectionFactory
    ) {
    }

    public function toOptionArray(): array
    {
        try {
            $collection = $this->authorCollectionFactory->create();
            $collection->addFieldToFilter('is_active', 1);
            $collection->setOrder('display_name', 'ASC');

            $options = [];
            foreach ($collection as $author) {
                $options[] = [
                    'value' => (int) $author->getAuthorId(),
                    'label' => (string) $author->getDisplayName(),
                ];
            }
            return $options;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
