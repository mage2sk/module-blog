<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model\Api;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\AuthorizationInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Model\Api\PublicReadGuard;
use PHPUnit\Framework\TestCase;

class PublicReadGuardTest extends TestCase
{
    private function guard(bool $allowed): PublicReadGuard
    {
        $authorization = $this->createStub(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturn($allowed);

        return new PublicReadGuard(
            $authorization,
            $this->createStub(StoreManagerInterface::class),
            $this->createStub(FilterBuilder::class),
            $this->createStub(FilterGroupBuilder::class)
        );
    }

    public function testCanReadAllFollowsAcl(): void
    {
        $this->assertTrue($this->guard(true)->canReadAll('Panth_Blog::post'));
        $this->assertFalse($this->guard(false)->canReadAll('Panth_Blog::post'));
    }

    public function testStoreVisibility(): void
    {
        $guard = $this->guard(false);
        $this->assertTrue($guard->isStoreVisible([], 2));
        $this->assertTrue($guard->isStoreVisible([0], 2));
        $this->assertTrue($guard->isStoreVisible([1, 2], 2));
        $this->assertFalse($guard->isStoreVisible([1], 2));
    }
}
