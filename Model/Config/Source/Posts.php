<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Panth\Blog\Model\ResourceModel\Post\CollectionFactory;

class Posts implements OptionSourceInterface
{
    public function __construct(
        private readonly CollectionFactory $postCollectionFactory
    ) {
    }

    public function toOptionArray(): array
    {
        try {
            $collection = $this->postCollectionFactory->create();
            $collection->addFieldToSelect(['post_id', 'title', 'status']);
            $collection->setOrder('title', 'ASC');

            $options = [];
            foreach ($collection as $post) {
                $id = (int) $post->getData('post_id');
                $options[] = [
                    'value' => $id,
                    'label' => sprintf('%s (#%d, %s)', (string) $post->getData('title'), $id, (string) $post->getData('status')),
                ];
            }
            return $options;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
