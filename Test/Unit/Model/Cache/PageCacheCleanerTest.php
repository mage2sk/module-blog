<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model\Cache;

use Magento\Framework\Event\ManagerInterface;
use Panth\Blog\Model\Cache\PageCacheCleaner;
use Panth\Blog\Model\Cache\TagSet;
use Panth\Blog\Model\Pagination\PageUrlBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PageCacheCleanerTest extends TestCase
{
    public function testDispatchesDeduplicatedNonEmptyTags(): void
    {
        $captured = null;
        $events = $this->createMock(ManagerInterface::class);
        $events->expects($this->once())->method('dispatch')
            ->with('clean_cache_by_tags', $this->callback(function (array $data) use (&$captured) {
                $captured = $data['object'];
                return true;
            }));

        (new PageCacheCleaner($events, $this->createStub(LoggerInterface::class)))
            ->clean(['a', '', 'b', 'a', 0, 7]);

        $this->assertInstanceOf(TagSet::class, $captured);
        $this->assertSame(['a', 'b', '7'], $captured->getIdentities());
    }

    public function testNothingDispatchedForEmptyTags(): void
    {
        $events = $this->createMock(ManagerInterface::class);
        $events->expects($this->never())->method('dispatch');
        (new PageCacheCleaner($events, $this->createStub(LoggerInterface::class)))->clean(['', null]);
    }

    public function testFailuresAreLogged(): void
    {
        $events = $this->createStub(ManagerInterface::class);
        $events->method('dispatch')->willThrowException(new \RuntimeException('boom'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($this->stringContains('boom'));

        (new PageCacheCleaner($events, $logger))->clean(['x']);
    }

    public function testTagSetDefaultsToEmpty(): void
    {
        $this->assertSame([], (new TagSet())->getIdentities());
    }

    public function testPageUrlBuilder(): void
    {
        $builder = new PageUrlBuilder();
        $this->assertSame('https://s.test/blog', $builder->build('https://s.test/blog/', 1));
        $this->assertSame('https://s.test/blog/page/3', $builder->build('https://s.test/blog/', 3));
        $this->assertSame('/blog', $builder->build('/blog', 0));
    }
}
