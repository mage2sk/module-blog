<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model\Post;

use Panth\Blog\Model\Cache\PageCacheCleaner;
use Panth\Blog\Model\Post\LinkManager;
use Panth\Blog\Model\ResourceModel\Post as PostResource;
use PHPUnit\Framework\TestCase;

class LinkManagerTest extends TestCase
{
    private array $writes = [];
    private array $cleaned = [];

    private function manager(array $current = []): LinkManager
    {
        $current += ['stores' => [], 'categories' => [], 'primary' => null, 'tags' => [], 'related' => []];
        $resource = $this->createStub(PostResource::class);
        $resource->method('getStoreIds')->willReturn($current['stores']);
        $resource->method('getCategoryIds')->willReturn($current['categories']);
        $resource->method('getPrimaryCategoryId')->willReturn($current['primary']);
        $resource->method('getTagIds')->willReturn($current['tags']);
        $resource->method('getRelatedIds')->willReturn($current['related']);
        foreach (['saveStoreLinks', 'saveTagLinks', 'saveRelatedLinks'] as $method) {
            $resource->method($method)->willReturnCallback(function (int $id, array $ids) use ($method) {
                $this->writes[$method] = [$id, $ids];
            });
        }
        $resource->method('saveCategoryLinks')->willReturnCallback(function (int $id, array $ids, ?int $primary) {
            $this->writes['saveCategoryLinks'] = [$id, $ids, $primary];
        });
        $cleaner = $this->createStub(PageCacheCleaner::class);
        $cleaner->method('clean')->willReturnCallback(function (array $tags) {
            $this->cleaned = $tags;
        });

        return new LinkManager($resource, $cleaner);
    }

    public function testFormDataForNewPostDefaultsToAllStoreViews(): void
    {
        $data = $this->manager()->getFormData(0);
        $this->assertSame(['0'], $data['store_ids']);
        $this->assertSame([], $data['category_ids']);
        $this->assertSame('', $data['primary_category_id']);
        $this->assertSame('1', $data[LinkManager::MARKER]);
    }

    public function testFormDataLoadsExistingLinksAsStrings(): void
    {
        $data = $this->manager([
            'stores' => [], 'categories' => [8, 9], 'primary' => 8, 'tags' => [5], 'related' => [14, 15],
        ])->getFormData(13);

        $this->assertSame(['0'], $data['store_ids']);
        $this->assertSame(['8', '9'], $data['category_ids']);
        $this->assertSame('8', $data['primary_category_id']);
        $this->assertSame(['5'], $data['tag_ids']);
        $this->assertSame(['14', '15'], $data['related_ids']);
    }

    public function testNothingIsWrittenWithoutTheFormMarker(): void
    {
        $this->assertFalse($this->manager()->save(13, ['store_ids' => ['2']]));
        $this->assertSame([], $this->writes);
    }

    public function testUnchangedSelectionWritesNothing(): void
    {
        $manager = $this->manager([
            'stores' => [], 'categories' => [9, 8], 'primary' => 8, 'tags' => [5, 6], 'related' => [14],
        ]);
        $changed = $manager->save(13, [
            LinkManager::MARKER => '1',
            'store_ids' => ['0'],
            'category_ids' => ['8', '9'],
            'primary_category_id' => '8',
            'tag_ids' => ['6', '5'],
            'related_ids' => ['14'],
        ]);

        $this->assertFalse($changed);
        $this->assertSame([], $this->writes);
        $this->assertSame([], $this->cleaned);
    }

    public function testChangedSelectionIsSavedAndCacheCleaned(): void
    {
        $manager = $this->manager(['stores' => [0], 'categories' => [8], 'primary' => 8, 'tags' => [], 'related' => []]);
        $changed = $manager->save(13, [
            LinkManager::MARKER => '1',
            'store_ids' => ['2', 'x'],
            'category_ids' => '9,10',
            'primary_category_id' => '10',
            'tag_ids' => ['7', '7', '-1'],
            'related_ids' => ['13', '14'],
        ]);

        $this->assertTrue($changed);
        $this->assertSame([13, [2]], $this->writes['saveStoreLinks']);
        $this->assertSame([13, [10, 9], 10], $this->writes['saveCategoryLinks']);
        $this->assertSame([13, [7]], $this->writes['saveTagLinks']);
        $this->assertSame([13, [14]], $this->writes['saveRelatedLinks']);
        $this->assertContains('panth_blog_post_13', $this->cleaned);
        $this->assertContains('panth_blog_category', $this->cleaned);
    }

    public function testEmptyStoresMeanAllStoreViewsAndPrimaryFallsBackToFirstCategory(): void
    {
        $manager = $this->manager(['stores' => [2], 'categories' => [], 'primary' => null]);
        $manager->save(13, [
            LinkManager::MARKER => '1',
            'store_ids' => '',
            'category_ids' => ['9', '8'],
            'primary_category_id' => '99',
        ]);

        $this->assertSame([13, [0]], $this->writes['saveStoreLinks']);
        $this->assertSame([13, [9, 8], 9], $this->writes['saveCategoryLinks']);
        $this->assertArrayNotHasKey('saveTagLinks', $this->writes);
    }

    public function testClearingCategoriesRemovesPrimary(): void
    {
        $manager = $this->manager(['stores' => [0], 'categories' => [8], 'primary' => 8]);
        $manager->save(13, [LinkManager::MARKER => '1', 'store_ids' => ['0'], 'category_ids' => '']);
        $this->assertSame([13, [], null], $this->writes['saveCategoryLinks']);
    }

    public function testInvalidPostIdIsIgnored(): void
    {
        $this->assertFalse($this->manager()->save(0, [LinkManager::MARKER => '1', 'store_ids' => ['2']]));
        $this->assertSame([], $this->writes);
    }
}
