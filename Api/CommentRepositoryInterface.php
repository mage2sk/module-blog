<?php
declare(strict_types=1);

namespace Panth\Blog\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\Blog\Api\Data\CommentInterface;
use Panth\Blog\Api\Data\CommentSearchResultsInterface;

/** Repository contract for Panth_Blog comment entities. */
interface CommentRepositoryInterface
{
    /**
     * Persist a comment.
     *
     * @param \Panth\Blog\Api\Data\CommentInterface $comment
     * @return \Panth\Blog\Api\Data\CommentInterface
     */
    public function save(CommentInterface $comment): CommentInterface;

    /**
     * Load a comment by primary key.
     *
     * @param int $id
     * @return \Panth\Blog\Api\Data\CommentInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $id): CommentInterface;

    /**
     * Delete a comment.
     *
     * @param \Panth\Blog\Api\Data\CommentInterface $comment
     * @return bool
     */
    public function delete(CommentInterface $comment): bool;

    /**
     * Delete a comment by primary key.
     *
     * @param int $id
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function deleteById(int $id): bool;

    /**
     * List comments matching the search criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Panth\Blog\Api\Data\CommentSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria);

    /**
     * List comments for a specific post, optionally filtered by status.
     *
     * @param int $postId
     * @param string|null $status
     * @return \Panth\Blog\Api\Data\CommentSearchResultsInterface
     */
    public function getListByPost(int $postId, ?string $status = null);
}
