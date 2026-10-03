<?php
declare(strict_types=1);

namespace Panth\Blog\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\Blog\Api\Data\AuthorInterface;
use Panth\Blog\Api\Data\AuthorSearchResultsInterface;

/** Repository contract for Panth_Blog author entities. */
interface AuthorRepositoryInterface
{
    /**
     * Persist an author.
     *
     * @param \Panth\Blog\Api\Data\AuthorInterface $author
     * @return \Panth\Blog\Api\Data\AuthorInterface
     */
    public function save(AuthorInterface $author): AuthorInterface;

    /**
     * Load an author by primary key.
     *
     * @param int $id
     * @return \Panth\Blog\Api\Data\AuthorInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $id): AuthorInterface;

    /**
     * Load an author by URL key (optionally scoped to a store view).
     *
     * @param string $urlKey
     * @param int|null $storeId
     * @return \Panth\Blog\Api\Data\AuthorInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByUrlKey(string $urlKey, ?int $storeId = null): AuthorInterface;

    /**
     * Delete an author.
     *
     * @param \Panth\Blog\Api\Data\AuthorInterface $author
     * @return bool
     */
    public function delete(AuthorInterface $author): bool;

    /**
     * Delete an author by primary key.
     *
     * @param int $id
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function deleteById(int $id): bool;

    /**
     * List authors matching the search criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Panth\Blog\Api\Data\AuthorSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria);
}
