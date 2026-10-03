<?php
declare(strict_types=1);

namespace Panth\Blog\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Post extends AbstractDb
{
    public const TABLE_POST_CATEGORY = 'panth_blog_post_category';
    public const TABLE_POST_TAG = 'panth_blog_post_tag';
    public const TABLE_POST_STORE = 'panth_blog_post_store';
    public const TABLE_POST_RELATED = 'panth_blog_post_related';

    protected function _construct(): void
    {
        $this->_init('panth_blog_post', 'post_id');
    }

    public function getCategoryIds(int $postId): array
    {
        if ($postId <= 0) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(self::TABLE_POST_CATEGORY), 'category_id')
            ->where('post_id = ?', $postId);
        $ids = $connection->fetchCol($select);
        return array_map('intval', is_array($ids) ? $ids : []);
    }

    public function getPrimaryCategoryId(int $postId): ?int
    {
        if ($postId <= 0) {
            return null;
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(self::TABLE_POST_CATEGORY), 'category_id')
            ->where('post_id = ?', $postId)
            ->where('is_primary = ?', 1)
            ->limit(1);
        $id = $connection->fetchOne($select);
        return $id ? (int) $id : null;
    }

    public function getTagIds(int $postId): array
    {
        if ($postId <= 0) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(self::TABLE_POST_TAG), 'tag_id')
            ->where('post_id = ?', $postId);
        $ids = $connection->fetchCol($select);
        return array_map('intval', is_array($ids) ? $ids : []);
    }

    public function getRelatedIds(int $postId): array
    {
        if ($postId <= 0) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(self::TABLE_POST_RELATED), 'related_post_id')
            ->where('post_id = ?', $postId)
            ->order('sort_order ASC');
        $ids = $connection->fetchCol($select);
        return array_map('intval', is_array($ids) ? $ids : []);
    }

    public function getStoreIds(int $postId): array
    {
        if ($postId <= 0) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(self::TABLE_POST_STORE), 'store_id')
            ->where('post_id = ?', $postId);
        $ids = $connection->fetchCol($select);
        return array_map('intval', is_array($ids) ? $ids : []);
    }

    public function saveCategoryLinks(int $postId, array $categoryIds, ?int $primaryId): void
    {
        if ($postId <= 0) {
            return;
        }
        $connection = $this->getConnection();
        $table = $this->getTable(self::TABLE_POST_CATEGORY);
        $connection->delete($table, ['post_id = ?' => $postId]);
        $position = 0;
        foreach (array_unique(array_map('intval', $categoryIds)) as $categoryId) {
            if ($categoryId <= 0) {
                continue;
            }
            $connection->insertOnDuplicate(
                $table,
                [
                    'post_id' => $postId,
                    'category_id' => $categoryId,
                    'position' => $position++,
                    'is_primary' => ($primaryId !== null && $categoryId === $primaryId) ? 1 : 0,
                ],
                ['position', 'is_primary']
            );
        }
    }

    public function saveTagLinks(int $postId, array $tagIds): void
    {
        if ($postId <= 0) {
            return;
        }
        $connection = $this->getConnection();
        $table = $this->getTable(self::TABLE_POST_TAG);
        $connection->delete($table, ['post_id = ?' => $postId]);
        foreach (array_unique(array_map('intval', $tagIds)) as $tagId) {
            if ($tagId <= 0) {
                continue;
            }
            $connection->insertOnDuplicate(
                $table,
                ['post_id' => $postId, 'tag_id' => $tagId],
                ['tag_id']
            );
        }
    }

    public function saveStoreLinks(int $postId, array $storeIds): void
    {
        if ($postId <= 0) {
            return;
        }
        $connection = $this->getConnection();
        $table = $this->getTable(self::TABLE_POST_STORE);
        $connection->delete($table, ['post_id = ?' => $postId]);
        foreach (array_unique(array_map('intval', $storeIds)) as $storeId) {
            if ($storeId < 0) {
                continue;
            }
            $connection->insertOnDuplicate(
                $table,
                ['post_id' => $postId, 'store_id' => $storeId],
                ['store_id']
            );
        }
    }

    public function saveRelatedLinks(int $postId, array $relatedIds): void
    {
        if ($postId <= 0) {
            return;
        }
        $connection = $this->getConnection();
        $table = $this->getTable(self::TABLE_POST_RELATED);
        $connection->delete($table, ['post_id = ?' => $postId]);
        $sortOrder = 0;
        foreach (array_unique(array_map('intval', $relatedIds)) as $relatedId) {
            if ($relatedId <= 0 || $relatedId === $postId) {
                continue;
            }
            $connection->insertOnDuplicate(
                $table,
                [
                    'post_id' => $postId,
                    'related_post_id' => $relatedId,
                    'sort_order' => $sortOrder++,
                ],
                ['sort_order']
            );
        }
    }
}
