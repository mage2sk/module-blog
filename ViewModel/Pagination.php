<?php
declare(strict_types=1);

namespace Panth\Blog\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;

class Pagination implements ArgumentInterface
{
    public function build(int $current, int $total, int $perPage, callable $urlBuilder): array
    {
        $perPage = max(1, $perPage);
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
        if ($totalPages < 1) {
            $totalPages = 1;
        }
        $current = max(1, min($current, $totalPages));

        if ($totalPages === 1) {
            return [
                'current'     => 1,
                'total_pages' => 1,
                'items'       => [[
                    'type'       => 'page',
                    'page'       => 1,
                    'url'        => $urlBuilder(1),
                    'is_current' => true,
                ]],
            ];
        }

        $pages = [1, 2, $totalPages - 1, $totalPages];
        for ($i = $current - 2; $i <= $current + 2; $i++) {
            $pages[] = $i;
        }
        $pages = array_values(array_unique(array_filter(
            $pages,
            static fn (int $n): bool => $n >= 1 && $n <= $totalPages
        )));
        sort($pages, SORT_NUMERIC);

        $items = [];

        if ($current > 1) {
            $items[] = [
                'type'       => 'prev',
                'page'       => $current - 1,
                'url'        => $urlBuilder($current - 1),
                'is_current' => false,
            ];
        } else {
            $items[] = [
                'type'       => 'prev',
                'page'       => null,
                'url'        => null,
                'is_current' => false,
            ];
        }

        $items[] = [
            'type'       => 'first',
            'page'       => 1,
            'url'        => $urlBuilder(1),
            'is_current' => $current === 1,
        ];

        $previous = 0;
        foreach ($pages as $page) {
            $page = (int) $page;
            if ($previous !== 0 && $page - $previous > 1) {
                $items[] = [
                    'type'       => 'ellipsis',
                    'page'       => null,
                    'url'        => null,
                    'is_current' => false,
                ];
            }
            $items[] = [
                'type'       => 'page',
                'page'       => $page,
                'url'        => $urlBuilder($page),
                'is_current' => $page === $current,
            ];
            $previous = $page;
        }

        $items[] = [
            'type'       => 'last',
            'page'       => $totalPages,
            'url'        => $urlBuilder($totalPages),
            'is_current' => $current === $totalPages,
        ];

        if ($current < $totalPages) {
            $items[] = [
                'type'       => 'next',
                'page'       => $current + 1,
                'url'        => $urlBuilder($current + 1),
                'is_current' => false,
            ];
        } else {
            $items[] = [
                'type'       => 'next',
                'page'       => null,
                'url'        => null,
                'is_current' => false,
            ];
        }

        return [
            'current'     => $current,
            'total_pages' => $totalPages,
            'items'       => $items,
        ];
    }
}
