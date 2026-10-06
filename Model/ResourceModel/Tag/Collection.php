<?php
declare(strict_types=1);

namespace Panth\Blog\Model\ResourceModel\Tag;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'tag_id';

    protected function _construct(): void
    {
        $this->_init(
            \Panth\Blog\Model\Tag::class,
            \Panth\Blog\Model\ResourceModel\Tag::class
        );
    }
}
