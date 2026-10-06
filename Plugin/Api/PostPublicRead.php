<?php
declare(strict_types=1);

namespace Panth\Blog\Plugin\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Model\Api\PublicReadGuard;
use Panth\Blog\Model\ResourceModel\Post as PostResource;

class PostPublicRead
{
    private const ACL_RESOURCE = 'Panth_Blog::post';

    public function __construct(
        private readonly PublicReadGuard $guard,
        private readonly PostResource $postResource
    ) {
    }

    public function beforeGetList(PostRepositoryInterface $subject, SearchCriteriaInterface $searchCriteria): array
    {
        if (!$this->guard->canReadAll(self::ACL_RESOURCE)) {
            $this->guard->addRequiredFilters($searchCriteria, [
                PostInterface::STATUS => PostInterface::STATUS_PUBLISHED,
                'store_id' => (string) $this->guard->getStoreId(),
            ]);
        }
        return [$searchCriteria];
    }

    public function afterGetById(PostRepositoryInterface $subject, PostInterface $result, int $id): PostInterface
    {
        if ($this->guard->canReadAll(self::ACL_RESOURCE)) {
            return $result;
        }
        $storeIds = $this->postResource->getStoreIds((int) $result->getPostId());
        if ($result->getStatus() !== PostInterface::STATUS_PUBLISHED
            || !$this->guard->isStoreVisible($storeIds, $this->guard->getStoreId())
        ) {
            throw new NoSuchEntityException(__('Blog post with ID "%1" does not exist.', $id));
        }
        return $result;
    }
}
