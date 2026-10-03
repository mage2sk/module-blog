<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Sitemap;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\UrlInterface;
use Panth\Blog\Helper\Config;

class BlogContributor
{
    private const TABLE_POST = 'panth_blog_post';
    private const TABLE_CATEGORY = 'panth_blog_category';
    private const TABLE_TAG = 'panth_blog_tag';
    private const TABLE_AUTHOR = 'panth_blog_author';

    private const SITEMAP_NAME = 'sitemap-blog-1.xml';

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly UrlInterface $url,
        private readonly Config $config
    ) {
    }

    public function getSitemapName(): string
    {
        return self::SITEMAP_NAME;
    }

    public function getUrls(int $storeId): array
    {
        $out = [];
        $connection = $this->resource->getConnection();

        $frontName = $this->config->getRouteFrontName();
        $baseUrl = $this->url->getBaseUrl(['_store' => $storeId]);
        $baseUrl = rtrim($baseUrl, '/');

        $postTable = $this->resource->getTableName(self::TABLE_POST);
        if ($connection->isTableExists($postTable)) {
            $select = $connection->select()
                ->from($postTable, ['url_key', 'updated_at', 'published_at'])
                ->where('status = ?', 'published');
            foreach ($connection->fetchAll($select) as $row) {
                $slug = (string) $row['url_key'];
                if ($slug === '') {
                    continue;
                }
                $lastmod = $this->normaliseLastmod(
                    (string) ($row['updated_at'] ?? ''),
                    (string) ($row['published_at'] ?? '')
                );
                $out[] = [
                    'loc'        => $baseUrl . '/' . $frontName . '/' . $slug,
                    'lastmod'    => $lastmod,
                    'changefreq' => 'weekly',
                    'priority'   => 0.7,
                ];
            }
        }

        $categoryTable = $this->resource->getTableName(self::TABLE_CATEGORY);
        if ($connection->isTableExists($categoryTable)) {
            $select = $connection->select()
                ->from($categoryTable, ['url_key', 'updated_at'])
                ->where('is_active = ?', 1);
            foreach ($connection->fetchAll($select) as $row) {
                $slug = (string) $row['url_key'];
                if ($slug === '') {
                    continue;
                }
                $out[] = [
                    'loc'        => $baseUrl . '/' . $frontName . '/category/' . $slug,
                    'lastmod'    => $this->normaliseLastmod((string) ($row['updated_at'] ?? ''), ''),
                    'changefreq' => 'monthly',
                    'priority'   => 0.5,
                ];
            }
        }

        $tagTable = $this->resource->getTableName(self::TABLE_TAG);
        if ($connection->isTableExists($tagTable)) {
            $threshold = $this->config->getTagThinThreshold($storeId);
            $select = $connection->select()
                ->from($tagTable, ['url_key', 'updated_at'])
                ->where('post_count >= ?', $threshold);
            foreach ($connection->fetchAll($select) as $row) {
                $slug = (string) $row['url_key'];
                if ($slug === '') {
                    continue;
                }
                $out[] = [
                    'loc'        => $baseUrl . '/' . $frontName . '/tag/' . $slug,
                    'lastmod'    => $this->normaliseLastmod((string) ($row['updated_at'] ?? ''), ''),
                    'changefreq' => 'monthly',
                    'priority'   => 0.4,
                ];
            }
        }

        $authorTable = $this->resource->getTableName(self::TABLE_AUTHOR);
        if ($connection->isTableExists($authorTable)) {
            $select = $connection->select()
                ->from($authorTable, ['url_key', 'updated_at'])
                ->where('is_active = ?', 1);
            foreach ($connection->fetchAll($select) as $row) {
                $slug = (string) $row['url_key'];
                if ($slug === '') {
                    continue;
                }
                $out[] = [
                    'loc'        => $baseUrl . '/' . $frontName . '/author/' . $slug,
                    'lastmod'    => $this->normaliseLastmod((string) ($row['updated_at'] ?? ''), ''),
                    'changefreq' => 'monthly',
                    'priority'   => 0.4,
                ];
            }
        }

        return $out;
    }

    private function normaliseLastmod(string $updatedAt, string $publishedAt): string
    {
        $candidate = $updatedAt !== '' ? $updatedAt : $publishedAt;
        $ts = $candidate !== '' ? strtotime($candidate) : false;
        if ($ts === false) {
            $ts = time();
        }
        return gmdate('c', $ts);
    }
}
