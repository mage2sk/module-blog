<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model;

use Panth\Blog\Api\Data\AuthorSearchResultsInterface;
use Panth\Blog\Api\Data\AuthorSearchResultsInterfaceFactory;
use Panth\Blog\Model\Author;
use Panth\Blog\Model\AuthorFactory;
use Panth\Blog\Model\AuthorRepository;
use Panth\Blog\Model\ResourceModel\Author as AuthorResource;
use Panth\Blog\Model\ResourceModel\Author\Collection;
use Panth\Blog\Model\ResourceModel\Author\CollectionFactory;
use Panth\Blog\Test\Unit\Support\RepositoryTestCase;
use Panth\Blog\Test\Unit\Support\UrlKeyRepositoryTests;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class AuthorRepositoryTest extends RepositoryTestCase
{
    use UrlKeyRepositoryTests;

    protected function resourceClass(): string
    {
        return AuthorResource::class;
    }

    protected function factoryClass(): string
    {
        return AuthorFactory::class;
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
        return AuthorSearchResultsInterfaceFactory::class;
    }

    protected function resultsClass(): string
    {
        return AuthorSearchResultsInterface::class;
    }

    protected function modelClass(): string
    {
        return Author::class;
    }

    protected function buildRepository(array $extra = []): AuthorRepository
    {
        return new AuthorRepository(
            $this->resource,
            $this->factory,
            $this->collectionFactory,
            $this->resultsFactory,
            $this->processor
        );
    }
}
