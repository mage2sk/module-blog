<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model;

use Panth\Blog\Model\Url\SlugGenerator;
use Panth\Blog\Api\Data\TagSearchResultsInterface;
use Panth\Blog\Api\Data\TagSearchResultsInterfaceFactory;
use Panth\Blog\Model\Tag;
use Panth\Blog\Model\TagFactory;
use Panth\Blog\Model\TagRepository;
use Panth\Blog\Model\ResourceModel\Tag as TagResource;
use Panth\Blog\Model\ResourceModel\Tag\Collection;
use Panth\Blog\Model\ResourceModel\Tag\CollectionFactory;
use Panth\Blog\Test\Unit\Support\RepositoryTestCase;
use Panth\Blog\Test\Unit\Support\UrlKeyRepositoryTests;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class TagRepositoryTest extends RepositoryTestCase
{
    use UrlKeyRepositoryTests;

    protected function resourceClass(): string
    {
        return TagResource::class;
    }

    protected function factoryClass(): string
    {
        return TagFactory::class;
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
        return TagSearchResultsInterfaceFactory::class;
    }

    protected function resultsClass(): string
    {
        return TagSearchResultsInterface::class;
    }

    protected function modelClass(): string
    {
        return Tag::class;
    }

    protected function buildRepository(array $extra = []): TagRepository
    {
        return new TagRepository(
            $this->resource,
            $this->factory,
            $this->collectionFactory,
            $this->resultsFactory,
            $this->processor,
            $extra['slug'] ?? new SlugGenerator()
        );
    }
}
