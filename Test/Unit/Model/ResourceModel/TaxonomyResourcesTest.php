<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model\ResourceModel;

use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Model\AbstractModel;
use Panth\Blog\Model\ResourceModel\Category;
use Panth\Blog\Model\ResourceModel\Comment;
use Panth\Blog\Model\ResourceModel\Tag;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class TaxonomyResourcesTest extends TestCase
{
    use BlogTestHelpers;

    private function resource(string $class, AdapterInterface $connection, string $mainTable): object
    {
        $resource = $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getTable', 'getMainTable'])
            ->getMock();
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTable')->willReturnArgument(0);
        $resource->method('getMainTable')->willReturn($mainTable);
        return $resource;
    }

    public function testCategoryStoreIds(): void
    {
        $connection = $this->connectionStub();
        $connection->method('fetchCol')->willReturn(['0', '2']);
        $resource = $this->resource(Category::class, $connection, 'panth_blog_category');
        $this->assertSame([0, 2], $resource->getStoreIds(3));
        $this->assertSame([], $resource->getStoreIds(0));
    }

    public function testCategorySaveStoreLinks(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('delete')->with('panth_blog_category_store', ['category_id = ?' => 3]);
        $rows = [];
        $connection->method('insertOnDuplicate')->willReturnCallback(function ($t, $data) use (&$rows) {
            $rows[] = $data;
            return 1;
        });
        $this->resource(Category::class, $connection, 'panth_blog_category')->saveStoreLinks(3, [1, '1', -1, 0]);
        $this->assertSame([['category_id' => 3, 'store_id' => 1], ['category_id' => 3, 'store_id' => 0]], $rows);
    }

    private function callAfterSave(Category $resource, AbstractModel $object): void
    {
        $method = new \ReflectionMethod(Category::class, '_afterSave');
        $method->invoke($resource, $object);
    }

    private function category(array $data): AbstractModel
    {
        $model = new class extends AbstractModel {
            public function __construct()
            {
            }
        };
        $model->setData($data);
        $model->setIdFieldName('category_id');
        return $model;
    }

    public function testAfterSaveComputesRootPath(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('update')
            ->with('panth_blog_category', ['path' => '8', 'level' => 1], ['category_id = ?' => 8]);
        $object = $this->category(['category_id' => 8, 'parent_id' => null, 'path' => '', 'level' => 0]);

        $this->callAfterSave($this->resource(Category::class, $connection, 'panth_blog_category'), $object);
        $this->assertSame('8', $object->getData('path'));
        $this->assertSame(1, $object->getData('level'));
    }

    public function testAfterSaveAppendsToParentPath(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($this->selectStub());
        $connection->method('fetchOne')->willReturn('1/4');
        $connection->expects($this->once())->method('update')
            ->with('panth_blog_category', ['path' => '1/4/9', 'level' => 3], ['category_id = ?' => 9]);
        $object = $this->category(['category_id' => 9, 'parent_id' => '4', 'path' => '9', 'level' => 1]);

        $this->callAfterSave($this->resource(Category::class, $connection, 'panth_blog_category'), $object);
        $this->assertSame('1/4/9', $object->getData('path'));
    }

    public function testAfterSaveFallsBackToParentIdWhenParentPathUnknown(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($this->selectStub());
        $connection->method('fetchOne')->willReturn(false);
        $connection->expects($this->once())->method('update')
            ->with('panth_blog_category', ['path' => '4/9', 'level' => 2], ['category_id = ?' => 9]);
        $this->callAfterSave(
            $this->resource(Category::class, $connection, 'panth_blog_category'),
            $this->category(['category_id' => 9, 'parent_id' => 4])
        );
    }

    public function testAfterSaveSkipsUpdateWhenPathUnchanged(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->never())->method('update');
        $this->callAfterSave(
            $this->resource(Category::class, $connection, 'panth_blog_category'),
            $this->category(['category_id' => 8, 'parent_id' => '', 'path' => '8', 'level' => 1])
        );
    }

    public function testTagIdByUrlKey(): void
    {
        $connection = $this->connectionStub();
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls('6', false);
        $resource = $this->resource(Tag::class, $connection, 'panth_blog_tag');
        $this->assertSame(6, $resource->getIdByUrlKey('php'));
        $this->assertNull($resource->getIdByUrlKey('missing'));
        $this->assertNull($resource->getIdByUrlKey(''));
    }

    public function testTagRecomputePostCountPersistsCount(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($this->selectStub());
        $connection->method('fetchOne')->willReturn('4');
        $connection->expects($this->once())->method('update')->with('panth_blog_tag', ['post_count' => 4], ['tag_id = ?' => 2]);
        $resource = $this->resource(Tag::class, $connection, 'panth_blog_tag');
        $this->assertSame(4, $resource->recomputePostCount(2));
        $this->assertSame(0, $resource->recomputePostCount(0));
    }

    public function testCommentCounters(): void
    {
        $select = $this->createMock(\Magento\Framework\DB\Select::class);
        $select->method('from')->willReturnSelf();
        $wheres = [];
        $select->method('where')->willReturnCallback(function ($cond, $value = null) use (&$wheres, $select) {
            $wheres[] = [$cond, $value];
            return $select;
        });
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls('5', '2', false);
        $resource = $this->resource(Comment::class, $connection, 'panth_blog_comment');

        $this->assertSame(5, $resource->countByPost(3));
        $this->assertSame(2, $resource->countByPost(3, ''));
        $this->assertSame(0, $resource->countRecentByIp('1.2.3.4', 60));
        $this->assertSame(0, $resource->countByPost(0));
        $this->assertSame(0, $resource->countRecentByIp('', 60));
        $this->assertSame(0, $resource->countRecentByIp('1.2.3.4', 0));

        $this->assertSame([
            ['post_id = ?', 3],
            ['status = ?', 'approved'],
            ['post_id = ?', 3],
            ['ip = ?', '1.2.3.4'],
            ['created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)', 60],
        ], $wheres);
    }
}
