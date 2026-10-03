<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Schema;

use Panth\Blog\Api\Data\CategoryInterface;

class CollectionPageBuilder
{
    public function buildBlogIndex(
        string $name,
        string $url,
        string $description,
        array $postListItems
    ): array {
        return [
            '@type' => 'Blog',
            'name' => $name,
            'url' => $url,
            'description' => $description,
            'blogPost' => $postListItems ?: null,
        ];
    }

    public function buildCategoryPage(
        CategoryInterface $category,
        string $url,
        array $itemListElements,
        int $total
    ): array {
        return [
            '@type' => 'CollectionPage',
            'name' => $category->getName(),
            'description' => $category->getDescription(),
            'url' => $url,
            'mainEntity' => [
                '@type' => 'ItemList',
                'numberOfItems' => $total,
                'itemListElement' => $itemListElements ?: null,
            ],
        ];
    }
}
