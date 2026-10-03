<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Api\SearchCriteria;

use Magento\Framework\Api\Filter;
use Magento\Framework\Api\SearchCriteria\CollectionProcessor\FilterProcessor\CustomFilterInterface;
use Magento\Framework\Data\Collection\AbstractDb;

class StoreVisibilityFilter implements CustomFilterInterface
{
    public function __construct(
        private readonly string $linkTable,
        private readonly string $idField
    ) {
    }

    public function apply(Filter $filter, AbstractDb $collection): bool
    {
        $storeIds = [0];
        foreach (explode(',', (string) $filter->getValue()) as $value) {
            $value = trim($value);
            if ($value !== '' && ctype_digit($value)) {
                $storeIds[] = (int) $value;
            }
        }
        $storeIds = array_values(array_unique($storeIds));

        $connection = $collection->getConnection();
        $table = $collection->getResource()->getTable($this->linkTable);
        $idField = $connection->quoteIdentifier($this->idField);

        $anyLink = $connection->select()
            ->from(['sv_any' => $table], [new \Zend_Db_Expr('1')])
            ->where('sv_any.' . $idField . ' = main_table.' . $idField);
        $matchingLink = $connection->select()
            ->from(['sv_match' => $table], [new \Zend_Db_Expr('1')])
            ->where('sv_match.' . $idField . ' = main_table.' . $idField)
            ->where('sv_match.store_id IN (?)', $storeIds);

        $collection->getSelect()->where(
            'NOT EXISTS (' . $anyLink . ') OR EXISTS (' . $matchingLink . ')'
        );

        return true;
    }
}
