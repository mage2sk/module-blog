<?php
declare(strict_types=1);

namespace Panth\Blog\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\Blog\Api\Data\CategoryInterface;
use Panth\Blog\Api\Data\CategorySearchResultsInterface;

/** Repository contract for Panth_Blog category entities. */
interface CategoryRepositoryInterface
{
    /**
     * Persist a category.
     *
     * @param \Panth\Blog\Api\Data\CategoryInterface $category
     * @return \Panth\Blog\Api\Data\CategoryInterface
     */
    public function save(CategoryInterface $category): CategoryInterface;

    /**
     * Load a category by primary key.
     *
     * @param int $id
     * @return \Panth\Blog\Api\Data\CategoryInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $id): CategoryInterface;

    /**
     * Load a category by URL key (optionally scoped to a store view).
     *
     * @param string $urlKey
     * @param int|null $storeId
     * @return \Panth\Blog\Api\Data\CategoryInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByUrlKey(string $urlKey, ?int $storeId = null): CategoryInterface;

    /**
     * Delete a category.
     *
     * @param \Panth\Blog\Api\Data\CategoryInterface $category
     * @return bool
     */
    public function delete(CategoryInterface $category): bool;

    /**
     * Delete a category by primary key.
     *
     * @param int $id
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function deleteById(int $id): bool;

    /**
     * List categories matching the search criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Panth\Blog\Api\Data\CategorySearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria);
}
