<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Panth\Blog\Model\ResourceModel\Tag\CollectionFactory;

class Tags implements OptionSourceInterface
{
    public function __construct(
        private readonly CollectionFactory $tagCollectionFactory
    ) {
    }

    public function toOptionArray(): array
    {
        try {
            $collection = $this->tagCollectionFactory->create();
            $collection->setOrder('name', 'ASC');

            $options = [];
            foreach ($collection as $tag) {
                $options[] = [
                    'value' => (int) $tag->getTagId(),
                    'label' => (string) $tag->getName(),
                ];
            }
            return $options;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
