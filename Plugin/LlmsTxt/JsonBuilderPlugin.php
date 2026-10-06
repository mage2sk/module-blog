<?php
declare(strict_types=1);

namespace Panth\Blog\Plugin\LlmsTxt;

use Magento\Framework\App\ResourceConnection;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Url\PostUrlBuilder;

class JsonBuilderPlugin
{
    private const TABLE_POST = 'panth_blog_post';
    private const DEFAULT_MAX_POSTS = 50;
    private const DESC_MAX_CHARS = 200;

    public function __construct(
        private readonly Config $config,
        private readonly ResourceConnection $resource,
        private readonly PostUrlBuilder $postUrl
    ) {
    }

    public function afterBuild($subject, string $result, int $storeId): string
    {
        if (!$this->config->isLlmsTxtIncludeEnabled($storeId)) {
            return $result;
        }

        $decoded = json_decode($result, true);
        if (!is_array($decoded)) {
            return $result;
        }

        $entries = $this->collectEntries($storeId);
        if ($entries === []) {
            return $result;
        }

        $section = [
            'code'    => 'blog',
            'label'   => 'Blog Posts',
            'summary' => 'Latest articles from this site.',
            'count'   => count($entries),
            'entries' => $entries,
        ];

        if (!isset($decoded['sections']) || !is_array($decoded['sections'])) {
            $decoded['sections'] = [];
        }
        $decoded['sections'][] = $section;

        $reencoded = json_encode(
            $decoded,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );
        return is_string($reencoded) ? $reencoded : $result;
    }

    private function collectEntries(int $storeId): array
    {
        try {
            $connection = $this->resource->getConnection();
            $table = $this->resource->getTableName(self::TABLE_POST);
            if (!$connection->isTableExists($table)) {
                return [];
            }

            $max = $this->config->getLlmsTxtMaxPosts($storeId);
            if ($max <= 0) {
                $max = self::DEFAULT_MAX_POSTS;
            }

            $select = $connection->select()
                ->from(
                    $table,
                    ['post_id', 'url_key', 'title', 'short_description', 'published_at']
                )
                ->where('status = ?', 'published')
                ->order('published_at DESC')
                ->limit($max);

            $rows = $connection->fetchAll($select);
        } catch (\Throwable) {
            return [];
        }

        $entries = [];
        $frontName = $this->config->getRouteFrontName();
        foreach ($rows as $row) {
            $slug = (string) ($row['url_key'] ?? '');
            $title = trim((string) ($row['title'] ?? ''));
            if ($slug === '' || $title === '') {
                continue;
            }
            $desc = mb_substr(
                trim((string) strip_tags((string) ($row['short_description'] ?? ''))),
                0,
                self::DESC_MAX_CHARS
            );
            $entries[] = [
                'url'      => '/' . $frontName . '/' . $slug,
                'label'    => $title,
                'type'     => 'blog_post',
                'score'    => 0.5,
                'summary'  => $desc,
                'metadata' => [
                    'published_at' => (string) ($row['published_at'] ?? ''),
                ],
            ];
        }
        return $entries;
    }
}
