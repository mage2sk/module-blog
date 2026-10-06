<?php
declare(strict_types=1);

namespace Panth\Blog\Model\ResourceModel;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Category extends AbstractDb
{
    public const TABLE_CATEGORY_STORE = 'panth_blog_category_store';

    protected function _construct(): void
    {
        $this->_init('panth_blog_category', 'category_id');
    }

    public function getStoreIds(int $categoryId): array
    {
        if ($categoryId <= 0) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(self::TABLE_CATEGORY_STORE), 'store_id')
            ->where('category_id = ?', $categoryId);
        $ids = $connection->fetchCol($select);
        return array_map('intval', is_array($ids) ? $ids : []);
    }

    public function saveStoreLinks(int $categoryId, array $storeIds): void
    {
        if ($categoryId <= 0) {
            return;
        }
        $connection = $this->getConnection();
        $table = $this->getTable(self::TABLE_CATEGORY_STORE);
        $connection->delete($table, ['category_id = ?' => $categoryId]);
        foreach (array_unique(array_map('intval', $storeIds)) as $storeId) {
            if ($storeId < 0) {
                continue;
            }
            $connection->insertOnDuplicate(
                $table,
                ['category_id' => $categoryId, 'store_id' => $storeId],
                ['store_id']
            );
        }
    }

    protected function _afterSave(AbstractModel $object)
    {
        $categoryId = (int) $object->getId();
        if ($categoryId > 0) {
            $parentId = $object->getData('parent_id');
            $parentId = ($parentId === null || $parentId === '') ? null : (int) $parentId;
            $resolved = $this->recomputePath($categoryId, $parentId);
            $currentPath = (string) $object->getData('path');
            $currentLevel = (int) $object->getData('level');
            if ($resolved['path'] !== $currentPath || $resolved['level'] !== $currentLevel) {
                $connection = $this->getConnection();
                $connection->update(
                    $this->getMainTable(),
                    ['path' => $resolved['path'], 'level' => $resolved['level']],
                    ['category_id = ?' => $categoryId]
                );
                $object->setData('path', $resolved['path']);
                $object->setData('level', $resolved['level']);
            }
        }
        return parent::_afterSave($object);
    }

    private function recomputePath(int $categoryId, ?int $parentId): array
    {
        if ($parentId === null || $parentId <= 0) {
            return ['path' => (string) $categoryId, 'level' => 1];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['path'])
            ->where('category_id = ?', $parentId)
            ->limit(1);
        $parentPath = (string) ($connection->fetchOne($select) ?: $parentId);
        $path = $parentPath . '/' . $categoryId;
        $level = count(array_filter(explode('/', $path), static fn ($p) => $p !== ''));
        return ['path' => $path, 'level' => $level];
    }
}
