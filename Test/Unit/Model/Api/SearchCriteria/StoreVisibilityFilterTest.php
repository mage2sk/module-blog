<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model\Api\SearchCriteria;

use Magento\Framework\Api\Filter;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb as ResourceDb;
use Panth\Blog\Model\Api\SearchCriteria\StoreVisibilityFilter;
use Panth\Blog\Model\ResourceModel\Post\Collection;
use PHPUnit\Framework\TestCase;

class StoreVisibilityFilterTest extends TestCase
{
    public function testAppliesExistsClauseWithSanitisedStoreIds(): void
    {
        $storeWhere = null;
        $inner = $this->createStub(Select::class);
        $inner->method('from')->willReturnSelf();
        $inner->method('where')->willReturnCallback(function ($cond, $value = null) use (&$storeWhere, $inner) {
            if (str_contains($cond, 'store_id IN')) {
                $storeWhere = $value;
            }
            return $inner;
        });
        $inner->method('__toString')->willReturn('SUB');

        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($inner);
        $connection->method('quoteIdentifier')->willReturnCallback(static fn ($v) => '`' . $v . '`');

        $resource = $this->createStub(ResourceDb::class);
        $resource->method('getTable')->willReturn('panth_blog_post_store');

        $outer = $this->createMock(Select::class);
        $outer->expects($this->once())->method('where')->with('NOT EXISTS (SUB) OR EXISTS (SUB)')->willReturnSelf();

        $collection = $this->createStub(Collection::class);
        $collection->method('getConnection')->willReturn($connection);
        $collection->method('getResource')->willReturn($resource);
        $collection->method('getSelect')->willReturn($outer);

        $filter = new Filter(['value' => '2, abc,0,2,-1, 5']);
        $this->assertTrue((new StoreVisibilityFilter('panth_blog_post_store', 'post_id'))->apply($filter, $collection));
        $this->assertSame([0, 2, 5], $storeWhere);
    }
}
