<?php
declare(strict_types=1);

namespace Panth\Blog\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\Blog\Api\CategoryRepositoryInterface;
use Panth\Blog\Api\Data\CategoryInterface;
use Panth\Blog\Api\Data\CategorySearchResultsInterface;
use Panth\Blog\Api\Data\CategorySearchResultsInterfaceFactory;
use Panth\Blog\Model\ResourceModel\Category as CategoryResource;
use Panth\Blog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;

class CategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(
        private readonly CategoryResource $resource,
        private readonly CategoryFactory $categoryFactory,
        private readonly CategoryCollectionFactory $collectionFactory,
        private readonly CategorySearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    public function save(CategoryInterface $category): CategoryInterface
    {
        try {
            $this->resource->save($category);
        } catch (\Throwable $e) {
            throw new CouldNotSaveException(__('Could not save the blog category: %1', $e->getMessage()), $e);
        }
        return $category;
    }

    public function getById(int $id): CategoryInterface
    {
        $category = $this->categoryFactory->create();
        $this->resource->load($category, $id);
        if (!$category->getId()) {
            throw new NoSuchEntityException(__('Blog category with ID "%1" does not exist.', $id));
        }
        return $category;
    }

    public function getByUrlKey(string $urlKey, ?int $storeId = null): CategoryInterface
    {
        if ($urlKey === '') {
            throw new NoSuchEntityException(__('Blog category URL key is required.'));
        }
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(CategoryInterface::URL_KEY, $urlKey)
            ->setPageSize(1)
            ->setCurPage(1);

        $category = $collection->getFirstItem();
        if (!$category || !$category->getId()) {
            throw new NoSuchEntityException(
                __('Blog category with URL key "%1" does not exist.', $urlKey)
            );
        }
        return $category;
    }

    public function delete(CategoryInterface $category): bool
    {
        try {
            $this->resource->delete($category);
        } catch (\Throwable $e) {
            throw new CouldNotDeleteException(__('Could not delete the blog category: %1', $e->getMessage()), $e);
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
