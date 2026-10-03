<?php
declare(strict_types=1);

namespace Panth\Blog\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Panth\Blog\Api\Data\PostInterface;

class Tag extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('panth_blog_tag', 'tag_id');
    }

    public function getIdByUrlKey(string $urlKey): ?int
    {
        if ($urlKey === '') {
            return null;
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), 'tag_id')
            ->where('url_key = ?', $urlKey)
            ->limit(1);
        $id = $connection->fetchOne($select);
        return $id ? (int) $id : null;
    }

    public function recomputePostCount(int $tagId): int
    {
        if ($tagId <= 0) {
            return 0;
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(['pt' => $this->getTable('panth_blog_post_tag')], [])
            ->join(
                ['p' => $this->getTable('panth_blog_post')],
                'pt.post_id = p.post_id',
                []
            )
            ->where('pt.tag_id = ?', $tagId)
            ->where('p.status = ?', PostInterface::STATUS_PUBLISHED)
            ->columns(['cnt' => new \Zend_Db_Expr('COUNT(DISTINCT pt.post_id)')]);
        $count = (int) ($connection->fetchOne($select) ?: 0);
        $connection->update(
            $this->getMainTable(),
            ['post_count' => $count],
            ['tag_id = ?' => $tagId]
        );
        return $count;
    }
}
