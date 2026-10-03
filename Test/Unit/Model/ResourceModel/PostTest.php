<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model\ResourceModel;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Panth\Blog\Model\ResourceModel\Post;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class PostTest extends TestCase
{
    use BlogTestHelpers;

    private function resource(AdapterInterface $connection): Post
    {
        $resource = $this->getMockBuilder(Post::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getTable'])
            ->getMock();
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTable')->willReturnArgument(0);
        return $resource;
    }

    public static function readers(): array
    {
        return [['getCategoryIds'], ['getTagIds'], ['getRelatedIds'], ['getStoreIds']];
    }

    #[DataProvider('readers')]
    public function testReadersCastIdsAndGuardInvalidPost(string $method): void
    {
        $connection = $this->connectionStub();
        $connection->method('fetchCol')->willReturn(['3', '9']);
        $resource = $this->resource($connection);

        $this->assertSame([3, 9], $resource->{$method}(5));
        $this->assertSame([], $resource->{$method}(0));
    }

    public function testPrimaryCategory(): void
    {
        $connection = $this->connectionStub();
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls('7', false);
        $resource = $this->resource($connection);

        $this->assertSame(7, $resource->getPrimaryCategoryId(1));
        $this->assertNull($resource->getPrimaryCategoryId(1));
        $this->assertNull($resource->getPrimaryCategoryId(0));
    }

    private function capturingConnection(array &$deletes, array &$inserts): AdapterInterface
    {
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('delete')->willReturnCallback(function ($table, $where) use (&$deletes) {
            $deletes[] = [$table, $where];
            return 1;
        });
        $connection->method('insertOnDuplicate')->willReturnCallback(function ($table, $data, $fields) use (&$inserts) {
            $inserts[] = [$table, $data, $fields];
            return 1;
        });
        return $connection;
    }

    public function testSaveCategoryLinksReplacesLinksAndFlagsPrimary(): void
    {
        $deletes = $inserts = [];
        $this->resource($this->capturingConnection($deletes, $inserts))->saveCategoryLinks(4, ['2', 5, 2, 0, -1], 5);

        $this->assertSame([['panth_blog_post_category', ['post_id = ?' => 4]]], $deletes);
        $this->assertSame([
            ['panth_blog_post_category', ['post_id' => 4, 'category_id' => 2, 'position' => 0, 'is_primary' => 0], ['position', 'is_primary']],
            ['panth_blog_post_category', ['post_id' => 4, 'category_id' => 5, 'position' => 1, 'is_primary' => 1], ['position', 'is_primary']],
        ], $inserts);
    }

    public function testSaveTagLinksSkipsInvalidIds(): void
    {
        $deletes = $inserts = [];
        $this->resource($this->capturingConnection($deletes, $inserts))->saveTagLinks(4, [1, 1, 0, 3]);
        $this->assertCount(1, $deletes);
        $this->assertSame([[4, 1], [4, 3]], array_map(static fn ($i) => [$i[1]['post_id'], $i[1]['tag_id']], $inserts));
    }

    public function testSaveStoreLinksAllowsAllStoreViewButNotNegative(): void
    {
        $deletes = $inserts = [];
        $this->resource($this->capturingConnection($deletes, $inserts))->saveStoreLinks(4, [0, 1, -2]);
        $this->assertSame([0, 1], array_map(static fn ($i) => $i[1]['store_id'], $inserts));
    }

    public function testSaveRelatedLinksExcludesSelfAndKeepsOrder(): void
    {
        $deletes = $inserts = [];
        $this->resource($this->capturingConnection($deletes, $inserts))->saveRelatedLinks(4, [9, 4, 7, 9]);
        $this->assertSame([
            ['post_id' => 4, 'related_post_id' => 9, 'sort_order' => 0],
            ['post_id' => 4, 'related_post_id' => 7, 'sort_order' => 1],
        ], array_column($inserts, 1));
    }

    public function testSaversIgnoreInvalidPost(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->never())->method('delete');
        $resource = $this->resource($connection);
        $resource->saveCategoryLinks(0, [1], 1);
        $resource->saveTagLinks(0, [1]);
        $resource->saveStoreLinks(-1, [1]);
        $resource->saveRelatedLinks(0, [1]);
    }
}
