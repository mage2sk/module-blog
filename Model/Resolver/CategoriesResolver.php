<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Resolver;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Panth\Blog\Api\CategoryRepositoryInterface;
use Panth\Blog\Model\StoreVisibility;

class CategoriesResolver implements ResolverInterface
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly StoreVisibility $storeVisibility
    ) {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): array
    {
        try {
            $this->searchCriteriaBuilder->addFilter('is_active', 1);
            $this->searchCriteriaBuilder->addFilter('store_id', (string) $this->storeVisibility->getCurrentStoreId());
            $criteria = $this->searchCriteriaBuilder->create();
            $results = $this->categoryRepository->getList($criteria);

            $out = [];
            foreach ($results->getItems() as $cat) {
                if (method_exists($cat, 'getData')) {
                    $out[] = $cat->getData();
                }
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }
}
