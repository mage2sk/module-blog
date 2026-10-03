<?php
declare(strict_types=1);

namespace Panth\Blog\Model\ResourceModel\Author;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'author_id';

    protected function _construct(): void
    {
        $this->_init(
            \Panth\Blog\Model\Author::class,
            \Panth\Blog\Model\ResourceModel\Author::class
        );
    }
}
