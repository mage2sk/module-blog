<?php
declare(strict_types=1);

namespace Panth\Blog\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Store\Model\StoreManagerInterface;

class StoreVisibility
{
    private const POST_LINK_TABLE = 'panth_blog_post_store';
    private const CATEGORY_LINK_TABLE = 'panth_blog_category_store';

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function getCurrentStoreId(): int
    {
        try {
            return (int) $this->storeManager->getStore()->getId();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function filterPosts(Select $select, string $idColumn, ?int $storeId = null): Select
    {
        return $this->apply($select, self::POST_LINK_TABLE, 'post_id', $idColumn, $storeId);
    }

    public function filterCategories(Select $select, string $idColumn, ?int $storeId = null): Select
    {
        return $this->apply($select, self::CATEGORY_LINK_TABLE, 'category_id', $idColumn, $storeId);
    }

    public function isPostVisible(int $postId, ?int $storeId = null): bool
    {
        return $this->isVisible(self::POST_LINK_TABLE, 'post_id', $postId, $storeId);
    }

    public function isCategoryVisible(int $categoryId, ?int $storeId = null): bool
    {
        return $this->isVisible(self::CATEGORY_LINK_TABLE, 'category_id', $categoryId, $storeId);
    }

    private function apply(Select $select, string $table, string $field, string $idColumn, ?int $storeId): Select
    {
        $storeIds = $this->storeIds($storeId);
        $connection = $this->resource->getConnection();
        $linkTable = $this->resource->getTableName($table);
        $anyLink = $connection->select()
            ->from(['sv_any' => $linkTable], [new \Zend_Db_Expr('1')])
            ->where('sv_any.' . $field . ' = ' . $idColumn);
        $matchingLink = $connection->select()
            ->from(['sv_match' => $linkTable], [new \Zend_Db_Expr('1')])
            ->where('sv_match.' . $field . ' = ' . $idColumn)
            ->where('sv_match.store_id IN (?)', $storeIds);
        $select->where('NOT EXISTS (' . $anyLink . ') OR EXISTS (' . $matchingLink . ')');
        return $select;
    }

    private function isVisible(string $table, string $field, int $id, ?int $storeId): bool
    {
        if ($id <= 0) {
            return false;
        }
        $connection = $this->resource->getConnection();
        $rows = $connection->fetchCol(
            $connection->select()
                ->from($this->resource->getTableName($table), ['store_id'])
                ->where($field . ' = ?', $id)
        );
        if ($rows === []) {
            return true;
        }
        $rows = array_map('intval', $rows);
        return array_intersect($rows, $this->storeIds($storeId)) !== [];
    }

    private function storeIds(?int $storeId): array
    {
        $storeId = $storeId ?? $this->getCurrentStoreId();
        return array_values(array_unique([0, max(0, $storeId)]));
    }
}
