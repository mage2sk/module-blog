<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Schema;

class BreadcrumbBuilder
{
    public function build(array $items): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(
                static fn ($i, $idx) => [
                    '@type' => 'ListItem',
                    'position' => $idx + 1,
                    'name' => $i['name'] ?? '',
                    'item' => $i['item'] ?? '',
                ],
                $items,
                array_keys($items)
            ),
        ];
    }
}
