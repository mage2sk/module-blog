<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\ViewModel;

use Panth\Blog\ViewModel\Pagination;
use PHPUnit\Framework\TestCase;

class PaginationTest extends TestCase
{
    private function build(int $current, int $total, int $perPage): array
    {
        return (new Pagination())->build($current, $total, $perPage, static fn (int $p) => '/blog/page/' . $p);
    }

    private function summary(array $result): array
    {
        return array_map(static function (array $item) {
            return $item['type'] . ($item['page'] !== null ? ':' . $item['page'] : '') . ($item['is_current'] ? '*' : '');
        }, $result['items']);
    }

    public function testSinglePage(): void
    {
        $result = $this->build(5, 3, 10);
        $this->assertSame(1, $result['current']);
        $this->assertSame(1, $result['total_pages']);
        $this->assertSame(['page:1*'], $this->summary($result));
        $this->assertSame('/blog/page/1', $result['items'][0]['url']);
    }

    public function testEmptyListingStillHasOnePage(): void
    {
        $this->assertSame(1, $this->build(1, 0, 0)['total_pages']);
    }

    public function testMiddlePageWithEllipses(): void
    {
        $result = $this->build(10, 200, 10);
        $this->assertSame(20, $result['total_pages']);
        $this->assertSame([
            'prev:9', 'first:1', 'page:1', 'page:2', 'ellipsis', 'page:8', 'page:9', 'page:10*', 'page:11', 'page:12',
            'ellipsis', 'page:19', 'page:20', 'last:20', 'next:11',
        ], $this->summary($result));
    }

    public function testFirstPageDisablesPrev(): void
    {
        $result = $this->build(1, 50, 10);
        $this->assertSame(['prev', 'first:1*', 'page:1*', 'page:2', 'page:3', 'page:4', 'page:5', 'last:5', 'next:2'], $this->summary($result));
        $this->assertNull($result['items'][0]['url']);
    }

    public function testCurrentIsClampedToLastPage(): void
    {
        $result = $this->build(99, 25, 10);
        $this->assertSame(3, $result['current']);
        $items = $result['items'];
        $this->assertSame('next', end($items)['type']);
        $this->assertNull(end($items)['url']);
        $this->assertTrue($items[count($items) - 2]['is_current']);
    }
}
