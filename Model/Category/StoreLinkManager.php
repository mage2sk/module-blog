<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Category;

use Panth\Blog\Model\Cache\PageCacheCleaner;
use Panth\Blog\Model\Category;
use Panth\Blog\Model\Post;
use Panth\Blog\Model\ResourceModel\Category as CategoryResource;

class StoreLinkManager
{
    public const MARKER = 'links_submitted';

    public function __construct(
        private readonly CategoryResource $categoryResource,
        private readonly PageCacheCleaner $pageCacheCleaner
    ) {
    }

    public function getFormData(int $categoryId): array
    {
        $stores = $categoryId > 0 ? $this->categoryResource->getStoreIds($categoryId) : [];

        return [
            'store_ids' => array_values(array_map('strval', $stores === [] ? [0] : $stores)),
            self::MARKER => '1',
        ];
    }

    public function save(int $categoryId, array $data): bool
    {
        if ($categoryId <= 0 || empty($data[self::MARKER])) {
            return false;
        }
        $raw = $data['store_ids'] ?? [];
        if (is_string($raw) || is_int($raw)) {
            $raw = $raw === '' ? [] : explode(',', (string) $raw);
        }
        $stores = [];
        foreach (is_array($raw) ? $raw : [] as $value) {
            if (is_numeric($value) && (int) $value >= 0) {
                $stores[(int) $value] = (int) $value;
            }
        }
        $stores = array_values($stores);
        if ($stores === [] || in_array(0, $stores, true)) {
            $stores = [0];
        }
        $current = $this->categoryResource->getStoreIds($categoryId);
        if ($current === []) {
            $current = [0];
        }
        $sortedNew = $stores;
        $sortedCurrent = array_map('intval', $current);
        sort($sortedNew);
        sort($sortedCurrent);
        if ($sortedNew === array_values(array_unique($sortedCurrent))) {
            return false;
        }
        $this->categoryResource->saveStoreLinks($categoryId, $stores);
        $this->pageCacheCleaner->clean([
            Category::CACHE_TAG . '_' . $categoryId,
            Category::CACHE_TAG,
            Post::CACHE_TAG,
        ]);

        return true;
    }
}
