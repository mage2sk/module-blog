<?php
declare(strict_types=1);

namespace Panth\Blog\Model\LlmsTxt;

use Magento\Framework\App\ResourceConnection;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Url\AuthorUrlBuilder;
use Panth\Blog\Model\Url\PostUrlBuilder;
use Panth\Blog\Model\StoreVisibility;

class BlogContributor
{
    private const TABLE_POST = 'panth_blog_post';

    private const DEFAULT_MAX_POSTS = 50;

    private const DESC_MAX_CHARS = 200;

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly PostUrlBuilder $postUrl,
        private readonly AuthorUrlBuilder $authorUrl,
        private readonly Config $config,
        private readonly StoreVisibility $storeVisibility
    ) {
    }

    public function render(int $storeId = 0): string
    {
        if (!$this->config->isLlmsTxtIncludeEnabled($storeId)) {
            return '';
        }

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE_POST);
        if (!$connection->isTableExists($table)) {
            return '';
        }

        $max = $this->config->getLlmsTxtMaxPosts($storeId);
        if ($max <= 0) {
            $max = self::DEFAULT_MAX_POSTS;
        }

        $select = $connection->select()
            ->from(
                $table,
                ['post_id', 'url_key', 'title', 'short_description']
            )
            ->where('status = ?', 'published')
            ->order('published_at DESC')
            ->limit($max);

        if ($storeId > 0) {
            $this->storeVisibility->filterPosts($select, $table . '.post_id', $storeId);
        }
        $rows = $connection->fetchAll($select);
        if ($rows === []) {
            return '';
        }

        $frontName = $this->config->getRouteFrontName();

        $out  = "\n## Blog Posts\n\n";
        $out .= "Latest articles from this site.\n\n";

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

            $line = '- [' . $title . '](/' . $frontName . '/' . $slug . ')';
            if ($desc !== '') {
                $line .= ' - ' . $desc;
            }
            $out .= $line . "\n";
        }

        return $out;
    }

    public function priority(): int
    {
        return 75;
    }
}
