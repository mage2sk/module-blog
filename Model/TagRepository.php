<?php
declare(strict_types=1);

namespace Panth\Blog\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\Blog\Api\Data\TagInterface;
use Panth\Blog\Api\Data\TagSearchResultsInterface;
use Panth\Blog\Api\Data\TagSearchResultsInterfaceFactory;
use Panth\Blog\Api\TagRepositoryInterface;
use Panth\Blog\Model\ResourceModel\Tag as TagResource;
use Panth\Blog\Model\ResourceModel\Tag\CollectionFactory as TagCollectionFactory;
use Panth\Blog\Model\Url\SlugGenerator;

class TagRepository implements TagRepositoryInterface
{
    public function __construct(
        private readonly TagResource $resource,
        private readonly TagFactory $tagFactory,
        private readonly TagCollectionFactory $collectionFactory,
        private readonly TagSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly SlugGenerator $slugGenerator
    ) {
    }

    public function save(TagInterface $tag): TagInterface
    {
        try {
            $this->resource->save($tag);
        } catch (\Throwable $e) {
            throw new CouldNotSaveException(__('Could not save the blog tag: %1', $e->getMessage()), $e);
        }
        return $tag;
    }

    public function getById(int $id): TagInterface
    {
        $tag = $this->tagFactory->create();
        $this->resource->load($tag, $id);
        if (!$tag->getId()) {
            throw new NoSuchEntityException(__('Blog tag with ID "%1" does not exist.', $id));
        }
        return $tag;
    }

    public function getByUrlKey(string $urlKey, ?int $storeId = null): TagInterface
    {
        if ($urlKey === '') {
            throw new NoSuchEntityException(__('Blog tag URL key is required.'));
        }
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(TagInterface::URL_KEY, $urlKey)
            ->setPageSize(1)
            ->setCurPage(1);

        $tag = $collection->getFirstItem();
        if (!$tag || !$tag->getId()) {
            throw new NoSuchEntityException(
                __('Blog tag with URL key "%1" does not exist.', $urlKey)
            );
        }
        return $tag;
    }

    public function getByName(string $name): TagInterface
    {
        $urlKey = $this->slugGenerator->generate($name);
        return $this->getByUrlKey($urlKey);
    }

    public function delete(TagInterface $tag): bool
    {
        try {
            $this->resource->delete($tag);
        } catch (\Throwable $e) {
            throw new CouldNotDeleteException(__('Could not delete the blog tag: %1', $e->getMessage()), $e);
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
