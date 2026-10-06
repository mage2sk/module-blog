<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model;

use Panth\Blog\Api\Data\PostSearchResultsInterface;
use Panth\Blog\Api\Data\PostSearchResultsInterfaceFactory;
use Panth\Blog\Model\Post;
use Panth\Blog\Model\PostFactory;
use Panth\Blog\Model\PostRepository;
use Panth\Blog\Model\ResourceModel\Post as PostResource;
use Panth\Blog\Model\ResourceModel\Post\Collection;
use Panth\Blog\Model\ResourceModel\Post\CollectionFactory;
use Panth\Blog\Test\Unit\Support\RepositoryTestCase;
use Panth\Blog\Test\Unit\Support\UrlKeyRepositoryTests;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class PostRepositoryTest extends RepositoryTestCase
{
    use UrlKeyRepositoryTests;

    protected function resourceClass(): string
    {
        return PostResource::class;
    }

    protected function factoryClass(): string
    {
        return PostFactory::class;
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
        return PostSearchResultsInterfaceFactory::class;
    }

    protected function resultsClass(): string
    {
        return PostSearchResultsInterface::class;
    }

    protected function modelClass(): string
    {
        return Post::class;
    }

    protected function buildRepository(array $extra = []): PostRepository
    {
        return new PostRepository(
            $this->resource,
            $this->factory,
            $this->collectionFactory,
            $this->resultsFactory,
            $this->processor
        );
    }
}
