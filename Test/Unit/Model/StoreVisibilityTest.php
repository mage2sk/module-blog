<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Model\StoreVisibility;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\TestCase;

class StoreVisibilityTest extends TestCase
{
    use BlogTestHelpers;

    private function visibility(AdapterInterface $connection, int|\Throwable $store = 1): StoreVisibility
    {
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $storeManager = $this->createStub(StoreManagerInterface::class);
        if ($store instanceof \Throwable) {
            $storeManager->method('getStore')->willThrowException($store);
        } else {
            $storeModel = $this->createStub(Store::class);
            $storeModel->method('getId')->willReturn($store);
            $storeManager->method('getStore')->willReturn($storeModel);
        }
        return new StoreVisibility($resource, $storeManager);
    }

    public function testCurrentStoreIdFallsBackToZeroOnError(): void
    {
        $this->assertSame(3, $this->visibility($this->connectionStub(), 3)->getCurrentStoreId());
        $this->assertSame(0, $this->visibility($this->connectionStub(), new \RuntimeException('x'))->getCurrentStoreId());
    }

    public function testInvalidIdsAreNeverVisible(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->never())->method('fetchCol');
        $visibility = $this->visibility($connection);
        $this->assertFalse($visibility->isPostVisible(0));
        $this->assertFalse($visibility->isCategoryVisible(-1));
    }

    public function testUnlinkedEntityIsVisibleEverywhere(): void
    {
        $connection = $this->connectionStub();
        $connection->method('fetchCol')->willReturn([]);
        $this->assertTrue($this->visibility($connection)->isPostVisible(5, 2));
    }

    public function testLinkedEntityVisibilityDependsOnStore(): void
    {
        $connection = $this->connectionStub();
        $connection->method('fetchCol')->willReturn(['2', '4']);
        $visibility = $this->visibility($connection, 4);

        $this->assertTrue($visibility->isPostVisible(5, 2));
        $this->assertFalse($visibility->isCategoryVisible(5, 3));
        $this->assertTrue($visibility->isCategoryVisible(5));
    }

    public function testAllStoreViewLinkIsVisibleEverywhere(): void
    {
        $connection = $this->connectionStub();
        $connection->method('fetchCol')->willReturn(['0']);
        $this->assertTrue($this->visibility($connection)->isPostVisible(5, 7));
    }

    public function testFilterPostsAddsExistsCondition(): void
    {
        $inner = $this->selectStub();
        $connection = $this->connectionStub($inner);
        $outer = $this->createMock(Select::class);
        $outer->expects($this->once())->method('where')
            ->with('NOT EXISTS (SELECT 1) OR EXISTS (SELECT 1)')
            ->willReturnSelf();

        $this->assertSame($outer, $this->visibility($connection)->filterPosts($outer, 'main_table.post_id', 2));
    }

    public function testFilterCategoriesUsesCategoryLinkTable(): void
    {
        $inner = $this->createStub(Select::class);
        $fromTables = [];
        $inner->method('from')->willReturnCallback(function ($table) use (&$fromTables, $inner) {
            $fromTables[] = $table;
            return $inner;
        });
        $inner->method('where')->willReturnSelf();
        $inner->method('__toString')->willReturn('SELECT 1');
        $connection = $this->connectionStub($inner);

        $outer = $this->createStub(Select::class);
        $outer->method('where')->willReturnSelf();
        $this->visibility($connection)->filterCategories($outer, 'c.category_id');

        $this->assertSame([['sv_any' => 'panth_blog_category_store'], ['sv_match' => 'panth_blog_category_store']], $fromTables);
    }
}
