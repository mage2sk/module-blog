<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model;

use Panth\Blog\Api\Data\CategorySearchResultsInterface;
use Panth\Blog\Api\Data\CategorySearchResultsInterfaceFactory;
use Panth\Blog\Model\Category;
use Panth\Blog\Model\CategoryFactory;
use Panth\Blog\Model\CategoryRepository;
use Panth\Blog\Model\ResourceModel\Category as CategoryResource;
use Panth\Blog\Model\ResourceModel\Category\Collection;
use Panth\Blog\Model\ResourceModel\Category\CollectionFactory;
use Panth\Blog\Test\Unit\Support\RepositoryTestCase;
use Panth\Blog\Test\Unit\Support\UrlKeyRepositoryTests;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class CategoryRepositoryTest extends RepositoryTestCase
{
    use UrlKeyRepositoryTests;

    protected function resourceClass(): string
    {
        return CategoryResource::class;
    }

    protected function factoryClass(): string
    {
        return CategoryFactory::class;
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
        return CategorySearchResultsInterfaceFactory::class;
    }

    protected function resultsClass(): string
    {
        return CategorySearchResultsInterface::class;
    }

    protected function modelClass(): string
    {
        return Category::class;
    }

    protected function buildRepository(array $extra = []): CategoryRepository
    {
        return new CategoryRepository(
            $this->resource,
            $this->factory,
            $this->collectionFactory,
            $this->resultsFactory,
            $this->processor
        );
    }
}
