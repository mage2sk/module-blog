<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Support;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;

trait UrlKeyRepositoryTests
{
    public function testGetByUrlKeyRejectsEmptyKey(): void
    {
        $this->collectionFactory->expects($this->never())->method('create');
        $this->expectException(NoSuchEntityException::class);
        $this->buildRepository()->getByUrlKey('');
    }

    public function testGetByUrlKeyReturnsFirstMatch(): void
    {
        $entity = $this->entity(8);
        $this->collectionFactory->expects($this->once())->method('create')
            ->willReturn($this->urlKeyCollection('my-key', $entity));
        $this->assertSame($entity, $this->buildRepository()->getByUrlKey('my-key'));
    }

    public function testGetByUrlKeyThrowsWhenNoMatch(): void
    {
        $this->collectionFactory->method('create')->willReturn($this->urlKeyCollection('nope', new DataObject()));
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessageMatches('/nope/');
        $this->buildRepository()->getByUrlKey('nope');
    }

    protected function urlKeyCollection(string $key, object $first): MockObject
    {
        $collection = $this->createMock($this->collectionClass());
        $collection->expects($this->once())->method('addFieldToFilter')->with('url_key', $key)->willReturnSelf();
        $collection->method('setPageSize')->with(1)->willReturnSelf();
        $collection->method('setCurPage')->with(1)->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($first);
        return $collection;
    }
}
