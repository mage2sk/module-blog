<?php
declare(strict_types=1);

namespace Panth\Blog\Cron;

use Magento\Framework\App\ResourceConnection;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Cache\PageCacheCleaner;
use Panth\Blog\Model\Category;
use Panth\Blog\Model\Tag;
use Psr\Log\LoggerInterface;

class RefreshPostCounts
{
    private const TABLE_POST = 'panth_blog_post';
    private const TABLE_CATEGORY = 'panth_blog_category';
    private const TABLE_TAG = 'panth_blog_tag';
    private const TABLE_POST_CATEGORY = 'panth_blog_post_category';
    private const TABLE_POST_TAG = 'panth_blog_post_tag';

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly PageCacheCleaner $pageCacheCleaner
    ) {
    }

    public function execute(): void
    {
        try {
            $connection = $this->resource->getConnection();
            $categoryTable = $this->resource->getTableName(self::TABLE_CATEGORY);
            $tagTable = $this->resource->getTableName(self::TABLE_TAG);
            $postTable = $this->resource->getTableName(self::TABLE_POST);
            $postCategoryTable = $this->resource->getTableName(self::TABLE_POST_CATEGORY);
            $postTagTable = $this->resource->getTableName(self::TABLE_POST_TAG);

            if (
                !$connection->isTableExists($categoryTable)
                || !$connection->isTableExists($tagTable)
                || !$connection->isTableExists($postTable)
                || !$connection->isTableExists($postCategoryTable)
                || !$connection->isTableExists($postTagTable)
            ) {
                return;
            }

            $categoryAffected = (int) $connection->query(
                "UPDATE {$categoryTable} c SET c.post_count = ("
                . "SELECT COUNT(DISTINCT pc.post_id) FROM {$postCategoryTable} pc "
                . "JOIN {$postTable} p ON p.post_id = pc.post_id AND p.status = ? "
                . "WHERE pc.category_id = c.category_id)",
                ['published']
            )->rowCount();

            $tagAffected = (int) $connection->query(
                "UPDATE {$tagTable} t SET t.post_count = ("
                . "SELECT COUNT(DISTINCT pt.post_id) FROM {$postTagTable} pt "
                . "JOIN {$postTable} p ON p.post_id = pt.post_id AND p.status = ? "
                . "WHERE pt.tag_id = t.tag_id)",
                ['published']
            )->rowCount();

            $threshold = $this->config->getTagThinThreshold();

            $thinAffected = $connection->update(
                $tagTable,
                ['meta_robots' => 'noindex,follow'],
                [
                    'post_count < ?'   => $threshold,
                    'meta_robots <> ?' => 'noindex,follow',
                ]
            );

            $richAffected = $connection->update(
                $tagTable,
                ['meta_robots' => 'index,follow'],
                [
                    'post_count >= ?'  => $threshold,
                    'meta_robots <> ?' => 'index,follow',
                ]
            );

            if ($categoryAffected + $tagAffected + $thinAffected + $richAffected > 0) {
                $this->pageCacheCleaner->clean([Category::CACHE_TAG, Tag::CACHE_TAG]);
            }

            if ($categoryAffected + $tagAffected + $thinAffected + $richAffected > 0) {
                $this->pageCacheCleaner->clean([Category::CACHE_TAG, Tag::CACHE_TAG]);
            }

            $this->logger->info(sprintf(
                '[PanthBlog RefreshPostCounts] categories=%d tags=%d thin=%d rich=%d threshold=%d',
                $categoryAffected,
                $tagAffected,
                $thinAffected,
                $richAffected,
                $threshold
            ));
        } catch (\Throwable $e) {
            $this->logger->warning('[PanthBlog RefreshPostCounts] ' . $e->getMessage());
        }
    }
}
