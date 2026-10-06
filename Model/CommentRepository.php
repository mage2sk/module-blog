<?php
declare(strict_types=1);

namespace Panth\Blog\Model;

use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\Blog\Api\CommentRepositoryInterface;
use Panth\Blog\Api\Data\CommentInterface;
use Panth\Blog\Api\Data\CommentSearchResultsInterface;
use Panth\Blog\Api\Data\CommentSearchResultsInterfaceFactory;
use Panth\Blog\Model\ResourceModel\Comment as CommentResource;
use Panth\Blog\Model\ResourceModel\Comment\CollectionFactory as CommentCollectionFactory;

class CommentRepository implements CommentRepositoryInterface
{
    public function __construct(
        private readonly CommentResource $resource,
        private readonly CommentFactory $commentFactory,
        private readonly CommentCollectionFactory $collectionFactory,
        private readonly CommentSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly FilterBuilder $filterBuilder,
        private readonly FilterGroupBuilder $filterGroupBuilder
    ) {
    }

    public function save(CommentInterface $comment): CommentInterface
    {
        try {
            $this->resource->save($comment);
        } catch (\Throwable $e) {
            throw new CouldNotSaveException(__('Could not save the blog comment: %1', $e->getMessage()), $e);
        }
        return $comment;
    }

    public function getById(int $id): CommentInterface
    {
        $comment = $this->commentFactory->create();
        $this->resource->load($comment, $id);
        if (!$comment->getId()) {
            throw new NoSuchEntityException(__('Blog comment with ID "%1" does not exist.', $id));
        }
        return $comment;
    }

    public function delete(CommentInterface $comment): bool
    {
        try {
            $this->resource->delete($comment);
        } catch (\Throwable $e) {
            throw new CouldNotDeleteException(__('Could not delete the blog comment: %1', $e->getMessage()), $e);
        }
        return true;
    }

    public function deleteById(int $id): bool
    {
        return $this->delete($this->getById($id));
    }

    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $results = $this->searchResultsFactory->create();
        $results->setSearchCriteria($searchCriteria);
        $results->setItems($collection->getItems());
        $results->setTotalCount($collection->getSize());
        return $results;
    }

    public function getListByPost(int $postId, ?string $status = null)
    {
        $filters = [];
        $filters[] = $this->filterBuilder
            ->setField(CommentInterface::POST_ID)
            ->setConditionType('eq')
            ->setValue($postId)
            ->create();
        if ($status !== null && $status !== '') {
            $filters[] = $this->filterBuilder
                ->setField(CommentInterface::STATUS)
                ->setConditionType('eq')
                ->setValue($status)
                ->create();
        }
        foreach ($filters as $filter) {
            $this->searchCriteriaBuilder->addFilter(
                $filter->getField(),
                $filter->getValue(),
                $filter->getConditionType()
            );
        }
        return $this->getList($this->searchCriteriaBuilder->create());
    }
}
