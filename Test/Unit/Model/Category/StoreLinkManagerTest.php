<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model\Category;

use Panth\Blog\Model\Cache\PageCacheCleaner;
use Panth\Blog\Model\Category\StoreLinkManager;
use Panth\Blog\Model\ResourceModel\Category as CategoryResource;
use PHPUnit\Framework\TestCase;

class StoreLinkManagerTest extends TestCase
{
    private ?array $written = null;
    private array $cleaned = [];

    private function manager(array $current): StoreLinkManager
    {
        $resource = $this->createStub(CategoryResource::class);
        $resource->method('getStoreIds')->willReturn($current);
        $resource->method('saveStoreLinks')->willReturnCallback(function (int $id, array $stores) {
            $this->written = [$id, $stores];
        });
        $cleaner = $this->createStub(PageCacheCleaner::class);
        $cleaner->method('clean')->willReturnCallback(function (array $tags) {
            $this->cleaned = $tags;
        });
        return new StoreLinkManager($resource, $cleaner);
    }

    public function testFormDataDefaultsToAllStoreViews(): void
    {
        $this->assertSame(['store_ids' => ['0'], 'links_submitted' => '1'], $this->manager([])->getFormData(11));
        $this->assertSame(['2'], $this->manager([2])->getFormData(11)['store_ids']);
        $this->assertSame(['0'], $this->manager([2])->getFormData(0)['store_ids']);
    }

    public function testUnchangedStoresAreNotRewritten(): void
    {
        $this->assertFalse($this->manager([2])->save(11, ['links_submitted' => '1', 'store_ids' => ['2']]));
        $this->assertFalse($this->manager([])->save(11, ['links_submitted' => '1', 'store_ids' => ['0']]));
        $this->assertNull($this->written);
    }

    public function testChangedStoresAreSavedAndPagesCleaned(): void
    {
        $this->assertTrue($this->manager([2])->save(11, ['links_submitted' => '1', 'store_ids' => ['1', '2', 'x']]));
        $this->assertSame([11, [1, 2]], $this->written);
        $this->assertContains('panth_blog_category_11', $this->cleaned);
    }

    public function testAllStoreViewsWinsAndMarkerIsRequired(): void
    {
        $this->manager([2])->save(11, ['links_submitted' => '1', 'store_ids' => '0,2']);
        $this->assertSame([11, [0]], $this->written);

        $this->written = null;
        $this->assertFalse($this->manager([2])->save(11, ['store_ids' => ['1']]));
        $this->assertNull($this->written);
    }
}
