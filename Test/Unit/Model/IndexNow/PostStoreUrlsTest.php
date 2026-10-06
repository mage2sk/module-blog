<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model\IndexNow;

use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\IndexNow\PostStoreUrls;
use Panth\Blog\Model\ResourceModel\Post as PostResource;
use PHPUnit\Framework\TestCase;

class PostStoreUrlsTest extends TestCase
{
    private function subject(array $assigned = []): PostStoreUrls
    {
        $stores = [];
        foreach ([1 => ['https://hyva.test/', true], 2 => ['https://luma.test/', true], 3 => ['https://off.test/', false]] as $id => [$base, $active]) {
            $store = $this->createStub(Store::class);
            $store->method('getId')->willReturn($id);
            $store->method('isActive')->willReturn($active);
            $store->method('getBaseUrl')->willReturn($base);
            $stores[$id] = $store;
        }
        $manager = $this->createStub(StoreManagerInterface::class);
        $manager->method('getStores')->willReturn($stores);
        $manager->method('getStore')->willReturnCallback(static fn ($id) => $stores[(int) $id]);
        $resource = $this->createStub(PostResource::class);
        $resource->method('getStoreIds')->willReturn($assigned);
        $config = $this->createStub(Config::class);
        $config->method('getValue')->willReturnCallback(static fn ($path, $storeId) => $storeId === 2 ? '/journal/' : '');

        return new PostStoreUrls($manager, $resource, $config);
    }

    public function testNormalizeTreatsEmptyOrZeroAsAllStoreViews(): void
    {
        $urls = $this->subject();
        $this->assertSame([0], $urls->normalize([]));
        $this->assertSame([0], $urls->normalize(''));
        $this->assertSame([0], $urls->normalize(['2', '0']));
        $this->assertSame([1, 2], $urls->normalize('2,1,x,-1'));
    }

    public function testAssignedStoreIdsComeFromTheLinkTable(): void
    {
        $this->assertSame([0], $this->subject([])->getAssignedStoreIds(5));
        $this->assertSame([2], $this->subject([2])->getAssignedStoreIds(5));
        $this->assertSame([0], $this->subject([2])->getAssignedStoreIds(0));
    }

    public function testAllStoreViewsResolveToEveryActiveStore(): void
    {
        $this->assertSame([1, 2], $this->subject()->resolveStoreViews([0]));
        $this->assertSame([2], $this->subject()->resolveStoreViews([2, 3]));
    }

    public function testUrlsUseEachStoreBaseUrlAndRoute(): void
    {
        $this->assertSame(
            [1 => 'https://hyva.test/blog/my-post', 2 => 'https://luma.test/journal/my-post'],
            $this->subject()->getUrls([0], '/my-post/')
        );
        $this->assertSame([2 => 'https://luma.test/journal/p'], $this->subject()->getUrls([2], 'p'));
        $this->assertSame([], $this->subject()->getUrls([0], ''));
    }
}
