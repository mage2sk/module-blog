<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model\Url;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Panth\Blog\Model\Url\UrlHistoryManager;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UrlHistoryManagerTest extends TestCase
{
    use BlogTestHelpers;

    private function manager(AdapterInterface $connection): UrlHistoryManager
    {
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        return new UrlHistoryManager($resource);
    }

    public static function ignoredChanges(): array
    {
        return [
            'empty old' => ['post', 1, '', 'new'],
            'empty new' => ['post', 1, 'old', ''],
            'unchanged' => ['post', 1, 'same', 'same'],
            'unknown type' => ['product', 1, 'old', 'new'],
            'bad id' => ['post', 0, 'old', 'new'],
        ];
    }

    #[DataProvider('ignoredChanges')]
    public function testRecordSlugChangeIgnoresInvalidInput(string $type, int $id, string $old, string $new): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->never())->method('insert');
        $connection->expects($this->never())->method('isTableExists');
        $this->manager($connection)->recordSlugChange($type, $id, $old, $new);
    }

    public function testRecordSlugChangeSkipsWhenTableMissing(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('isTableExists')->willReturn(false);
        $connection->expects($this->never())->method('insert');
        $this->manager($connection)->recordSlugChange('tag', 3, 'old', 'new');
    }

    public function testRecordSlugChangeInsertsRow(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('isTableExists')->willReturn(true);
        $connection->expects($this->once())->method('insert')->with('panth_blog_url_history', [
            'entity_type' => 'category',
            'entity_id' => 4,
            'old_url_key' => 'old',
            'new_url_key' => 'new',
        ]);
        $this->manager($connection)->recordSlugChange('category', 4, 'old', 'new');
    }

    public function testFindCurrentRejectsInvalidInput(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->never())->method('fetchRow');
        $manager = $this->manager($connection);
        $this->assertNull($manager->findCurrent('post', ''));
        $this->assertNull($manager->findCurrent('cms', 'x'));
    }

    public function testFindCurrentReturnsNullWhenTableMissingOrNoRow(): void
    {
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('isTableExists')->willReturnOnConsecutiveCalls(false, true);
        $connection->method('select')->willReturn($this->selectStub());
        $connection->method('fetchRow')->willReturn(false);
        $manager = $this->manager($connection);

        $this->assertNull($manager->findCurrent('post', 'old'));
        $this->assertNull($manager->findCurrent('post', 'old'));
    }

    public function testFindCurrentCastsRow(): void
    {
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('isTableExists')->willReturn(true);
        $connection->method('select')->willReturn($this->selectStub());
        $connection->method('fetchRow')->willReturn(['entity_id' => '12', 'new_url_key' => 'fresh']);

        $this->assertSame(['entity_id' => 12, 'new_url_key' => 'fresh'], $this->manager($connection)->findCurrent('author', 'stale'));
    }
}
