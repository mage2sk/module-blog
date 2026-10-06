<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Post;

use Panth\Blog\Model\Cache\PageCacheCleaner;
use Panth\Blog\Model\Category;
use Panth\Blog\Model\Post;
use Panth\Blog\Model\ResourceModel\Post as PostResource;
use Panth\Blog\Model\Tag;

class LinkManager
{
    public const MARKER = 'links_submitted';

    public function __construct(
        private readonly PostResource $postResource,
        private readonly PageCacheCleaner $pageCacheCleaner
    ) {
    }

    public function getFormData(int $postId): array
    {
        if ($postId <= 0) {
            return [
                'store_ids' => ['0'],
                'category_ids' => [],
                'primary_category_id' => '',
                'tag_ids' => [],
                'related_ids' => [],
                self::MARKER => '1',
            ];
        }
        $stores = $this->postResource->getStoreIds($postId);
        $primary = $this->postResource->getPrimaryCategoryId($postId);

        return [
            'store_ids' => $this->toStrings($stores === [] ? [0] : $stores),
            'category_ids' => $this->toStrings($this->postResource->getCategoryIds($postId)),
            'primary_category_id' => $primary !== null ? (string) $primary : '',
            'tag_ids' => $this->toStrings($this->postResource->getTagIds($postId)),
            'related_ids' => $this->toStrings($this->postResource->getRelatedIds($postId)),
            self::MARKER => '1',
        ];
    }

    public function save(int $postId, array $data): bool
    {
        if ($postId <= 0 || empty($data[self::MARKER])) {
            return false;
        }
        $changed = false;

        $stores = $this->ids($data['store_ids'] ?? [], true);
        if ($stores === [] || in_array(0, $stores, true)) {
            $stores = [0];
        }
        $currentStores = $this->postResource->getStoreIds($postId);
        if ($currentStores === []) {
            $currentStores = [0];
        }
        if (!$this->sameSet($stores, $currentStores)) {
            $this->postResource->saveStoreLinks($postId, $stores);
            $changed = true;
        }

        $categories = $this->ids($data['category_ids'] ?? []);
        $primary = (int) ($data['primary_category_id'] ?? 0);
        if (!in_array($primary, $categories, true)) {
            $primary = $categories[0] ?? 0;
        }
        if ($primary > 0) {
            $categories = array_values(array_unique(array_merge([$primary], $categories)));
        }
        $currentPrimary = (int) $this->postResource->getPrimaryCategoryId($postId);
        if (!$this->sameSet($categories, $this->postResource->getCategoryIds($postId))
            || $primary !== $currentPrimary
        ) {
            $this->postResource->saveCategoryLinks($postId, $categories, $primary > 0 ? $primary : null);
            $changed = true;
        }

        $tags = $this->ids($data['tag_ids'] ?? []);
        if (!$this->sameSet($tags, $this->postResource->getTagIds($postId))) {
            $this->postResource->saveTagLinks($postId, $tags);
            $changed = true;
        }

        $related = array_values(array_filter(
            $this->ids($data['related_ids'] ?? []),
            static fn (int $id): bool => $id !== $postId
        ));
        if (!$this->sameSet($related, $this->postResource->getRelatedIds($postId))) {
            $this->postResource->saveRelatedLinks($postId, $related);
            $changed = true;
        }

        if ($changed) {
            $this->pageCacheCleaner->clean([
                Post::CACHE_TAG . '_' . $postId,
                Post::CACHE_TAG,
                Category::CACHE_TAG,
                Tag::CACHE_TAG,
            ]);
        }

        return $changed;
    }

    private function ids(mixed $raw, bool $allowZero = false): array
    {
        if (is_string($raw) || is_int($raw)) {
            $raw = $raw === '' ? [] : explode(',', (string) $raw);
        }
        if (!is_array($raw)) {
            return [];
        }
        $ids = [];
        foreach ($raw as $value) {
            if (!is_numeric($value)) {
                continue;
            }
            $id = (int) $value;
            if ($id > 0 || ($allowZero && $id === 0)) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private function sameSet(array $left, array $right): bool
    {
        $left = array_map('intval', $left);
        $right = array_map('intval', $right);
        sort($left);
        sort($right);

        return array_values(array_unique($left)) === array_values(array_unique($right));
    }

    private function toStrings(array $ids): array
    {
        return array_values(array_map('strval', $ids));
    }
}
