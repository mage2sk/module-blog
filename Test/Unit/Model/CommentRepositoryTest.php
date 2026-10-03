<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Panth\Blog\Api\Data\CommentSearchResultsInterface;
use Panth\Blog\Api\Data\CommentSearchResultsInterfaceFactory;
use Panth\Blog\Model\Comment;
use Panth\Blog\Model\CommentFactory;
use Panth\Blog\Model\CommentRepository;
use Panth\Blog\Model\ResourceModel\Comment as CommentResource;
use Panth\Blog\Model\ResourceModel\Comment\Collection;
use Panth\Blog\Model\ResourceModel\Comment\CollectionFactory;
use Panth\Blog\Test\Unit\Support\RepositoryTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class CommentRepositoryTest extends RepositoryTestCase
{
    protected function resourceClass(): string
    {
        return CommentResource::class;
    }

    protected function factoryClass(): string
    {
        return CommentFactory::class;
    }

    protected function collectionFactoryClass(): string
    {
        return CollectionFactory::class;
    }

    protected function collectionClass(): string
    {
        return Collection::class;
    }

    protected function resultsFactoryClass(): string
    {
        return CommentSearchResultsInterfaceFactory::class;
    }

    protected function resultsClass(): string
    {
        return CommentSearchResultsInterface::class;
    }

    protected function modelClass(): string
    {
        return Comment::class;
    }

    protected function buildRepository(array $extra = []): CommentRepository
    {
        return new CommentRepository(
            $this->resource,
            $this->factory,
            $this->collectionFactory,
            $this->resultsFactory,
            $this->processor,
            $extra['criteriaBuilder'] ?? $this->createStub(SearchCriteriaBuilder::class),
            $extra['filterBuilder'] ?? $this->createStub(FilterBuilder::class),
            $extra['filterGroupBuilder'] ?? $this->createStub(FilterGroupBuilder::class)
        );
    }
}
