<?php
declare(strict_types=1);

namespace Panth\Blog\Plugin\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\Blog\Api\AuthorRepositoryInterface;
use Panth\Blog\Api\Data\AuthorInterface;
use Panth\Blog\Model\Api\PublicReadGuard;

class AuthorPublicRead
{
    private const ACL_RESOURCE = 'Panth_Blog::author';

    private const PRIVATE_FIELDS = [AuthorInterface::EMAIL, AuthorInterface::USER_ID];

    public function __construct(
        private readonly PublicReadGuard $guard
    ) {
    }

    public function beforeGetList(AuthorRepositoryInterface $subject, SearchCriteriaInterface $searchCriteria): array
    {
        if (!$this->guard->canReadAll(self::ACL_RESOURCE)) {
            $this->guard->assertFieldsNotUsed($searchCriteria, self::PRIVATE_FIELDS);
            $this->guard->addRequiredFilters($searchCriteria, [AuthorInterface::IS_ACTIVE => 1]);
        }
        return [$searchCriteria];
    }

    public function afterGetList(AuthorRepositoryInterface $subject, $result)
    {
        if ($this->guard->canReadAll(self::ACL_RESOURCE)) {
            return $result;
        }
        foreach ((array) $result->getItems() as $author) {
            $this->stripPrivateData($author);
        }
        return $result;
    }

    public function afterGetById(AuthorRepositoryInterface $subject, AuthorInterface $result, int $id): AuthorInterface
    {
        if ($this->guard->canReadAll(self::ACL_RESOURCE)) {
            return $result;
        }
        if ((int) $result->getIsActive() !== 1) {
            throw new NoSuchEntityException(__('Blog author with ID "%1" does not exist.', $id));
        }
        $this->stripPrivateData($result);
        return $result;
    }

    private function stripPrivateData(AuthorInterface $author): void
    {
        $author->setEmail(null);
        $author->setUserId(null);
    }
}
