<?php
declare(strict_types=1);

namespace Panth\Blog\Model\IndexNow;

use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\ResourceModel\Post as PostResource;

class PostStoreUrls
{
    private const FRONT_NAME_DEFAULT = 'blog';

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly PostResource $postResource,
        private readonly Config $config
    ) {
    }

    public function getAssignedStoreIds(int $postId): array
    {
        return $postId > 0 ? $this->normalize($this->postResource->getStoreIds($postId)) : [0];
    }

    public function normalize(mixed $storeIds): array
    {
        if (is_string($storeIds) || is_int($storeIds)) {
            $storeIds = $storeIds === '' ? [] : explode(',', (string) $storeIds);
        }
        $ids = [];
        foreach (is_array($storeIds) ? $storeIds : [] as $value) {
            if (is_numeric($value) && (int) $value >= 0) {
                $ids[(int) $value] = (int) $value;
            }
        }
        if ($ids === [] || isset($ids[0])) {
            return [0];
        }
        $ids = array_values($ids);
        sort($ids);

        return $ids;
    }

    public function resolveStoreViews(array $assigned): array
    {
        $assigned = $this->normalize($assigned);
        $views = [];
        foreach ($this->storeManager->getStores(false) as $store) {
            $id = (int) $store->getId();
            if ($id <= 0 || !$store->isActive()) {
                continue;
            }
            if ($assigned === [0] || in_array($id, $assigned, true)) {
                $views[] = $id;
            }
        }
        sort($views);

        return $views;
    }

    public function getUrls(array $assigned, string $urlKey): array
    {
        $urlKey = trim($urlKey, '/');
        if ($urlKey === '') {
            return [];
        }
        $urls = [];
        foreach ($this->resolveStoreViews($assigned) as $storeId) {
            $base = rtrim((string) $this->storeManager->getStore($storeId)->getBaseUrl(UrlInterface::URL_TYPE_LINK), '/');
            $front = trim((string) $this->config->getValue('general/route_frontname', $storeId), '/');
            if ($base === '') {
                continue;
            }
            $urls[$storeId] = $base . '/' . ($front !== '' ? $front : self::FRONT_NAME_DEFAULT) . '/' . $urlKey;
        }

        return $urls;
    }
}
