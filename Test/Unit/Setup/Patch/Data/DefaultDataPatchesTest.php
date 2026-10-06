<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Setup\Patch\Data;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Panth\Blog\Setup\Patch\Data\InstallDefaultAuthor;
use Panth\Blog\Setup\Patch\Data\InstallDefaultCategories;
use PHPUnit\Framework\TestCase;

class DefaultDataPatchesTest extends TestCase
{
    private array $queries = [];

    private function resource(bool $tableExists, bool $failInserts = false): ResourceConnection
    {
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('isTableExists')->willReturn($tableExists);
        $connection->method('quoteIdentifier')->willReturnCallback(static fn ($t) => '`' . $t . '`');
        $connection->method('query')->willReturnCallback(function ($sql, $bind = []) use ($failInserts) {
            $this->queries[] = [$sql, $bind];
            if ($failInserts && str_starts_with($sql, 'INSERT')) {
                throw new \RuntimeException('duplicate');
            }
            return $this->createStub(\Zend_Db_Statement_Interface::class);
        });
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        return $resource;
    }

    public function testDefaultCategoriesAreInsertedAndPathsBackfilled(): void
    {
        $patch = new InstallDefaultCategories($this->resource(true));
        $this->assertSame($patch, $patch->apply());

        $this->assertCount(4, $this->queries);
        $this->assertSame(['general', 'General', 10], $this->queries[0][1]);
        $this->assertSame(['tutorials', 'Tutorials', 20], $this->queries[1][1]);
        $this->assertSame(['news', 'News', 30], $this->queries[2][1]);
        $this->assertStringStartsWith('INSERT IGNORE INTO `panth_blog_category`', $this->queries[0][0]);
        $this->assertStringStartsWith('UPDATE `panth_blog_category` SET path = CAST(category_id AS CHAR)', $this->queries[3][0]);
        $this->assertSame([], InstallDefaultCategories::getDependencies());
        $this->assertSame([], $patch->getAliases());
    }

    public function testCategoryInsertFailuresDoNotStopPathBackfill(): void
    {
        (new InstallDefaultCategories($this->resource(true, true)))->apply();
        $this->assertCount(4, $this->queries);
        $this->assertStringStartsWith('UPDATE', $this->queries[3][0]);
    }

    public function testPatchesSkipMissingTables(): void
    {
        (new InstallDefaultCategories($this->resource(false)))->apply();
        (new InstallDefaultAuthor($this->resource(false)))->apply();
        $this->assertSame([], $this->queries);
    }

    public function testDefaultAuthorIsInserted(): void
    {
        $patch = new InstallDefaultAuthor($this->resource(true));
        $this->assertSame($patch, $patch->apply());
        $this->assertSame([['INSERT IGNORE INTO `panth_blog_author` (url_key, display_name, role, short_bio, is_active) VALUES (?, ?, ?, ?, 1)', ['admin', 'Admin', 'Editor', '']]], $this->queries);

        $this->queries = [];
        (new InstallDefaultAuthor($this->resource(true, true)))->apply();
        $this->assertCount(1, $this->queries);
    }
}
