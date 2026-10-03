<?php
declare(strict_types=1);

namespace Panth\Blog\Model\ResourceModel\Category;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'category_id';

    protected function _construct(): void
    {
        $this->_init(
            \Panth\Blog\Model\Category::class,
            \Panth\Blog\Model\ResourceModel\Category::class
        );
    }
}
