<?php
declare(strict_types=1);

namespace Panth\Blog\Model\HtmlSitemap;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\UrlInterface;
use Panth\Blog\Helper\Config;

class BlogContributor
{
    private const TABLE_POST = 'panth_blog_post';
    private const TABLE_CATEGORY = 'panth_blog_category';
    private const TABLE_AUTHOR = 'panth_blog_author';

    private const MAX_POSTS = 200;

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly UrlInterface $url,
        private readonly Config $config
    ) {
    }

    public function getSection(int $storeId = 0): array
    {
        $connection = $this->resource->getConnection();
        $frontName = $this->config->getRouteFrontName();
        $baseUrl = rtrim($this->url->getBaseUrl(['_store' => $storeId]), '/');

        $section = [
            'title'    => 'Blog',
            'priority' => 70,
            'groups'   => [],
        ];

        $postTable = $this->resource->getTableName(self::TABLE_POST);
        if ($connection->isTableExists($postTable)) {
            $rows = $connection->fetchAll(
                $connection->select()
                    ->from($postTable, ['url_key', 'title'])
                    ->where('status = ?', 'published')
                    ->order('published_at DESC')
                    ->limit(self::MAX_POSTS)
            );
            $links = [];
            foreach ($rows as $row) {
                $slug = (string) ($row['url_key'] ?? '');
                $title = (string) ($row['title'] ?? '');
                if ($slug === '' || $title === '') {
                    continue;
                }
                $links[] = [
                    'url'   => $baseUrl . '/' . $frontName . '/' . $slug,
                    'label' => $title,
                ];
            }
            if ($links !== []) {
                $section['groups'][] = ['title' => 'Posts', 'links' => $links];
            }
        }

        $categoryTable = $this->resource->getTableName(self::TABLE_CATEGORY);
        if ($connection->isTableExists($categoryTable)) {
            $rows = $connection->fetchAll(
                $connection->select()
                    ->from($categoryTable, ['url_key', 'name'])
                    ->where('is_active = ?', 1)
                    ->order('sort_order ASC')
            );
            $links = [];
            foreach ($rows as $row) {
                $slug = (string) ($row['url_key'] ?? '');
                $name = (string) ($row['name'] ?? '');
                if ($slug === '' || $name === '') {
                    continue;
                }
                $links[] = [
                    'url'   => $baseUrl . '/' . $frontName . '/category/' . $slug,
                    'label' => $name,
                ];
            }
            if ($links !== []) {
                $section['groups'][] = ['title' => 'Categories', 'links' => $links];
            }
        }

        $authorTable = $this->resource->getTableName(self::TABLE_AUTHOR);
        if ($connection->isTableExists($authorTable)) {
            $rows = $connection->fetchAll(
                $connection->select()
                    ->from($authorTable, ['url_key', 'display_name'])
                    ->where('is_active = ?', 1)
                    ->order('sort_order ASC')
            );
            $links = [];
            foreach ($rows as $row) {
                $slug = (string) ($row['url_key'] ?? '');
                $name = (string) ($row['display_name'] ?? '');
                if ($slug === '' || $name === '') {
                    continue;
                }
                $links[] = [
                    'url'   => $baseUrl . '/' . $frontName . '/author/' . $slug,
                    'label' => $name,
                ];
            }
            if ($links !== []) {
                $section['groups'][] = ['title' => 'Authors', 'links' => $links];
            }
        }

        if ($section['groups'] === []) {
            return [];
        }

        return $section;
    }
}
