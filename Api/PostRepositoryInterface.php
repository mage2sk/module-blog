<?php
declare(strict_types=1);

namespace Panth\Blog\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Api\Data\PostSearchResultsInterface;

/** Repository contract for Panth_Blog post entities. */
interface PostRepositoryInterface
{
    /**
     * Persist a post.
     *
     * @param \Panth\Blog\Api\Data\PostInterface $post
     * @return \Panth\Blog\Api\Data\PostInterface
     */
    public function save(PostInterface $post): PostInterface;

    /**
     * Load a post by primary key.
     *
     * @param int $id
     * @return \Panth\Blog\Api\Data\PostInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $id): PostInterface;

    /**
     * Load a post by URL key (optionally scoped to a store view).
     *
     * @param string $urlKey
     * @param int|null $storeId
     * @return \Panth\Blog\Api\Data\PostInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByUrlKey(string $urlKey, ?int $storeId = null): PostInterface;

    /**
     * Delete a post.
     *
     * @param \Panth\Blog\Api\Data\PostInterface $post
     * @return bool
     */
    public function delete(PostInterface $post): bool;

    /**
     * Delete a post by primary key.
     *
     * @param int $id
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function deleteById(int $id): bool;

    /**
     * List posts matching the search criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Panth\Blog\Api\Data\PostSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria);
}
