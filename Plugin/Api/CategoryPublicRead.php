<?php
declare(strict_types=1);

namespace Panth\Blog\Plugin\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\Blog\Api\CategoryRepositoryInterface;
use Panth\Blog\Api\Data\CategoryInterface;
use Panth\Blog\Model\Api\PublicReadGuard;
use Panth\Blog\Model\ResourceModel\Category as CategoryResource;

class CategoryPublicRead
{
    private const ACL_RESOURCE = 'Panth_Blog::category';

    public function __construct(
        private readonly PublicReadGuard $guard,
        private readonly CategoryResource $categoryResource
    ) {
    }

    public function beforeGetList(
        CategoryRepositoryInterface $subject,
        SearchCriteriaInterface $searchCriteria
    ): array {
        if (!$this->guard->canReadAll(self::ACL_RESOURCE)) {
            $this->guard->addRequiredFilters($searchCriteria, [
                CategoryInterface::IS_ACTIVE => 1,
                'store_id' => (string) $this->guard->getStoreId(),
            ]);
        }
        return [$searchCriteria];
    }

    public function afterGetById(
        CategoryRepositoryInterface $subject,
        CategoryInterface $result,
        int $id
    ): CategoryInterface {
        if ($this->guard->canReadAll(self::ACL_RESOURCE)) {
            return $result;
        }
        $storeIds = $this->categoryResource->getStoreIds((int) $result->getCategoryId());
        if ((int) $result->getIsActive() !== 1
            || !$this->guard->isStoreVisible($storeIds, $this->guard->getStoreId())
        ) {
            throw new NoSuchEntityException(__('Blog category with ID "%1" does not exist.', $id));
        }
        return $result;
    }
}
