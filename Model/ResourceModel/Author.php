<?php
declare(strict_types=1);

namespace Panth\Blog\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Author extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('panth_blog_author', 'author_id');
    }

    public function getIdByUrlKey(string $urlKey): ?int
    {
        if ($urlKey === '') {
            return null;
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), 'author_id')
            ->where('url_key = ?', $urlKey)
            ->limit(1);
        $id = $connection->fetchOne($select);
        return $id ? (int) $id : null;
    }
}
