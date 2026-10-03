<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Support;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

abstract class RepositoryTestCase extends TestCase
{
    use BlogTestHelpers;

    protected MockObject $resource;
    protected MockObject $factory;
    protected MockObject $collectionFactory;
    protected MockObject $resultsFactory;
    protected MockObject $processor;

    abstract protected function resourceClass(): string;

    abstract protected function factoryClass(): string;

    abstract protected function collectionFactoryClass(): string;

    abstract protected function collectionClass(): string;

    abstract protected function resultsFactoryClass(): string;

    abstract protected function resultsClass(): string;

    abstract protected function modelClass(): string;

    abstract protected function buildRepository(array $extra = []): object;

    protected function setUp(): void
    {
        $this->resource = $this->getMockBuilder($this->resourceClass())->disableOriginalConstructor()->getMock();
        $this->factory = $this->getMockBuilder($this->factoryClass())->disableOriginalConstructor()->onlyMethods(['create'])->getMock();
        $this->collectionFactory = $this->getMockBuilder($this->collectionFactoryClass())->disableOriginalConstructor()->onlyMethods(['create'])->getMock();
        $this->resultsFactory = $this->getMockBuilder($this->resultsFactoryClass())->disableOriginalConstructor()->onlyMethods(['create'])->getMock();
        $this->processor = $this->createMock(CollectionProcessorInterface::class);
    }

    protected function entity(?int $id): object
    {
        $model = $this->makeModel($this->modelClass());
        if ($id !== null) {
            $model->setId($id);
        }
        return $model;
    }

    public function testSaveDelegatesToResource(): void
    {
        $entity = $this->entity(1);
        $this->resource->expects($this->once())->method('save')->with($entity);
        $this->assertSame($entity, $this->buildRepository()->save($entity));
    }

    public function testSaveWrapsFailures(): void
    {
        $this->resource->expects($this->once())->method('save')->willThrowException(new \RuntimeException('db down'));
        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessageMatches('/db down/');
        $this->buildRepository()->save($this->entity(1));
    }

    public function testGetByIdReturnsLoadedEntity(): void
    {
        $entity = $this->entity(null);
        $this->factory->expects($this->once())->method('create')->willReturn($entity);
        $this->resource->expects($this->once())->method('load')->with($entity, 5)
            ->willReturnCallback(function ($object) {
                $object->setId(5);
                return $this->resource;
            });
        $this->assertSame($entity, $this->buildRepository()->getById(5));
    }

    public function testGetByIdThrowsWhenMissing(): void
    {
        $this->factory->expects($this->once())->method('create')->willReturn($this->entity(null));
        $this->resource->expects($this->once())->method('load');
        $this->expectException(NoSuchEntityException::class);
        $this->buildRepository()->getById(99);
    }

    public function testDeleteDelegatesAndWrapsFailures(): void
    {
        $entity = $this->entity(3);
        $this->resource->expects($this->exactly(2))->method('delete')
            ->willReturnOnConsecutiveCalls($this->resource, $this->throwException(new \RuntimeException('locked')));
        $repository = $this->buildRepository();
        $this->assertTrue($repository->delete($entity));

        $this->expectException(CouldNotDeleteException::class);
        $this->expectExceptionMessageMatches('/locked/');
        $repository->delete($entity);
    }

    public function testDeleteByIdLoadsThenDeletes(): void
    {
        $entity = $this->entity(null);
        $this->factory->method('create')->willReturn($entity);
        $this->resource->method('load')->willReturnCallback(function ($object) {
            $object->setId(4);
            return $this->resource;
        });
        $this->resource->expects($this->once())->method('delete')->with($entity);
        $this->assertTrue($this->buildRepository()->deleteById(4));
    }

    public function testGetListBuildsSearchResults(): void
    {
        $criteria = $this->createStub(SearchCriteriaInterface::class);
        $items = [$this->entity(1), $this->entity(2)];
        $collection = $this->createMock($this->collectionClass());
        $collection->method('getItems')->willReturn($items);
        $collection->method('getSize')->willReturn(2);
        $this->collectionFactory->expects($this->once())->method('create')->willReturn($collection);
        $this->processor->expects($this->once())->method('process')->with($criteria, $collection);

        $results = $this->createMock($this->resultsClass());
        $results->expects($this->once())->method('setSearchCriteria')->with($criteria);
        $results->expects($this->once())->method('setItems')->with($items);
        $results->expects($this->once())->method('setTotalCount')->with(2);
        $this->resultsFactory->expects($this->once())->method('create')->willReturn($results);

        $this->assertSame($results, $this->buildRepository()->getList($criteria));
    }
}
