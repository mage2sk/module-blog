<?php
declare(strict_types=1);

namespace Panth\Blog\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Panth\Blog\Api\Data\CommentInterface;

class Comment extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('panth_blog_comment', 'comment_id');
    }

    public function countByPost(int $postId, string $status = CommentInterface::STATUS_APPROVED): int
    {
        if ($postId <= 0) {
            return 0;
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['cnt' => new \Zend_Db_Expr('COUNT(*)')])
            ->where('post_id = ?', $postId);
        if ($status !== '') {
            $select->where('status = ?', $status);
        }
        return (int) ($connection->fetchOne($select) ?: 0);
    }

    public function countRecentByIp(string $ip, int $seconds = 3600): int
    {
        if ($ip === '' || $seconds <= 0) {
            return 0;
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['cnt' => new \Zend_Db_Expr('COUNT(*)')])
            ->where('ip = ?', $ip)
            ->where('created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)', $seconds);
        return (int) ($connection->fetchOne($select) ?: 0);
    }
}
