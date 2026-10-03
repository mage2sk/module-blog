<?php
declare(strict_types=1);

namespace Panth\Blog\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Api\Data\PostSearchResultsInterface;
use Panth\Blog\Api\Data\PostSearchResultsInterfaceFactory;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Model\ResourceModel\Post as PostResource;
use Panth\Blog\Model\ResourceModel\Post\CollectionFactory as PostCollectionFactory;

class PostRepository implements PostRepositoryInterface
{
    public function __construct(
        private readonly PostResource $resource,
        private readonly PostFactory $postFactory,
        private readonly PostCollectionFactory $collectionFactory,
        private readonly PostSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    public function save(PostInterface $post): PostInterface
    {
        try {
            $this->resource->save($post);
        } catch (\Throwable $e) {
            throw new CouldNotSaveException(__('Could not save the blog post: %1', $e->getMessage()), $e);
        }
        return $post;
    }

    public function getById(int $id): PostInterface
    {
        $post = $this->postFactory->create();
        $this->resource->load($post, $id);
        if (!$post->getId()) {
            throw new NoSuchEntityException(__('Blog post with ID "%1" does not exist.', $id));
        }
        return $post;
    }

    public function getByUrlKey(string $urlKey, ?int $storeId = null): PostInterface
    {
        if ($urlKey === '') {
            throw new NoSuchEntityException(__('Blog post URL key is required.'));
        }
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(PostInterface::URL_KEY, $urlKey)
            ->setPageSize(1)
            ->setCurPage(1);

        $post = $collection->getFirstItem();
        if (!$post || !$post->getId()) {
            throw new NoSuchEntityException(
                __('Blog post with URL key "%1" does not exist.', $urlKey)
            );
        }
        return $post;
    }

    public function delete(PostInterface $post): bool
    {
        try {
            $this->resource->delete($post);
        } catch (\Throwable $e) {
            throw new CouldNotDeleteException(__('Could not delete the blog post: %1', $e->getMessage()), $e);
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
}
