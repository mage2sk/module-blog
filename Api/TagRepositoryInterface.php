<?php
declare(strict_types=1);

namespace Panth\Blog\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\Blog\Api\Data\TagInterface;
use Panth\Blog\Api\Data\TagSearchResultsInterface;

/** Repository contract for Panth_Blog tag entities. */
interface TagRepositoryInterface
{
    /**
     * Persist a tag.
     *
     * @param \Panth\Blog\Api\Data\TagInterface $tag
     * @return \Panth\Blog\Api\Data\TagInterface
     */
    public function save(TagInterface $tag): TagInterface;

    /**
     * Load a tag by primary key.
     *
     * @param int $id
     * @return \Panth\Blog\Api\Data\TagInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $id): TagInterface;

    /**
     * Load a tag by URL key (optionally scoped to a store view).
     *
     * @param string $urlKey
     * @param int|null $storeId
     * @return \Panth\Blog\Api\Data\TagInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByUrlKey(string $urlKey, ?int $storeId = null): TagInterface;

    /**
     * Load a tag by display name.
     *
     * @param string $name
     * @return \Panth\Blog\Api\Data\TagInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByName(string $name): TagInterface;

    /**
     * Delete a tag.
     *
     * @param \Panth\Blog\Api\Data\TagInterface $tag
     * @return bool
     */
    public function delete(TagInterface $tag): bool;

    /**
     * Delete a tag by primary key.
     *
     * @param int $id
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function deleteById(int $id): bool;

    /**
     * List tags matching the search criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Panth\Blog\Api\Data\TagSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria);
}
