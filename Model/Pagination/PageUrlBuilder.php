<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Pagination;

class PageUrlBuilder
{
    public function build(string $base, int $page): string
    {
        $base = rtrim($base, '/');

        return $page > 1 ? $base . '/page/' . $page : $base;
    }
}
