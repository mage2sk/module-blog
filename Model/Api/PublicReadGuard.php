<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Api;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Exception\InputException;
use Magento\Store\Model\StoreManagerInterface;

class PublicReadGuard
{
    public function __construct(
        private readonly AuthorizationInterface $authorization,
        private readonly StoreManagerInterface $storeManager,
        private readonly FilterBuilder $filterBuilder,
        private readonly FilterGroupBuilder $filterGroupBuilder
    ) {
    }

    public function canReadAll(string $aclResource): bool
    {
        try {
            return $this->authorization->isAllowed($aclResource);
        } catch (\Throwable) {
            return false;
        }
    }

    public function getStoreId(): int
    {
        try {
            return (int) $this->storeManager->getStore()->getId();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function isStoreVisible(array $storeIds, int $storeId): bool
    {
        if ($storeIds === []) {
            return true;
        }
        $storeIds = array_map('intval', $storeIds);
        return in_array(0, $storeIds, true) || in_array($storeId, $storeIds, true);
    }

    public function addRequiredFilters(SearchCriteriaInterface $searchCriteria, array $filters): void
    {
        $groups = (array) $searchCriteria->getFilterGroups();
        foreach ($filters as $field => $value) {
            $filter = $this->filterBuilder
                ->setField((string) $field)
                ->setValue($value)
                ->setConditionType('eq')
                ->create();
            $groups[] = $this->filterGroupBuilder->setFilters([$filter])->create();
        }
        $searchCriteria->setFilterGroups($groups);
    }

    public function assertFieldsNotUsed(SearchCriteriaInterface $searchCriteria, array $fields): void
    {
        $used = [];
        foreach ((array) $searchCriteria->getFilterGroups() as $group) {
            foreach ((array) $group->getFilters() as $filter) {
                $used[] = (string) $filter->getField();
            }
        }
        foreach ((array) $searchCriteria->getSortOrders() as $sortOrder) {
            $used[] = (string) $sortOrder->getField();
        }
        foreach ($used as $field) {
            if (in_array(strtolower(trim($field)), $fields, true)) {
                throw new InputException(__('Filtering or sorting by "%1" is not allowed.', $field));
            }
        }
    }
}
