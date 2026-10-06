<?php
declare(strict_types=1);

namespace Panth\Blog\Model\ResourceModel\Comment;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'comment_id';

    protected function _construct(): void
    {
        $this->_init(
            \Panth\Blog\Model\Comment::class,
            \Panth\Blog\Model\ResourceModel\Comment::class
        );
    }
}
