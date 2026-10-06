<?php
declare(strict_types=1);

namespace Panth\Blog\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\Blog\Api\AuthorRepositoryInterface;
use Panth\Blog\Api\Data\AuthorInterface;
use Panth\Blog\Api\Data\AuthorSearchResultsInterface;
use Panth\Blog\Api\Data\AuthorSearchResultsInterfaceFactory;
use Panth\Blog\Model\ResourceModel\Author as AuthorResource;
use Panth\Blog\Model\ResourceModel\Author\CollectionFactory as AuthorCollectionFactory;

class AuthorRepository implements AuthorRepositoryInterface
{
    public function __construct(
        private readonly AuthorResource $resource,
        private readonly AuthorFactory $authorFactory,
        private readonly AuthorCollectionFactory $collectionFactory,
        private readonly AuthorSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    public function save(AuthorInterface $author): AuthorInterface
    {
        try {
            $this->resource->save($author);
        } catch (\Throwable $e) {
            throw new CouldNotSaveException(__('Could not save the blog author: %1', $e->getMessage()), $e);
        }
        return $author;
    }

    public function getById(int $id): AuthorInterface
    {
        $author = $this->authorFactory->create();
        $this->resource->load($author, $id);
        if (!$author->getId()) {
            throw new NoSuchEntityException(__('Blog author with ID "%1" does not exist.', $id));
        }
        return $author;
    }

    public function getByUrlKey(string $urlKey, ?int $storeId = null): AuthorInterface
    {
        if ($urlKey === '') {
            throw new NoSuchEntityException(__('Blog author URL key is required.'));
        }
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(AuthorInterface::URL_KEY, $urlKey)
            ->setPageSize(1)
            ->setCurPage(1);

        $author = $collection->getFirstItem();
        if (!$author || !$author->getId()) {
            throw new NoSuchEntityException(
                __('Blog author with URL key "%1" does not exist.', $urlKey)
            );
        }
        return $author;
    }

    public function delete(AuthorInterface $author): bool
    {
        try {
            $this->resource->delete($author);
        } catch (\Throwable $e) {
            throw new CouldNotDeleteException(__('Could not delete the blog author: %1', $e->getMessage()), $e);
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
