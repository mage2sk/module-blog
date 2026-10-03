<?php
declare(strict_types=1);

namespace Panth\Blog\Cron;

use Magento\Framework\App\ResourceConnection;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Psr\Log\LoggerInterface;

class PublishScheduledPosts
{
    private const TABLE = 'panth_blog_post';

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly PostRepositoryInterface $postRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        try {
            $connection = $this->resource->getConnection();
            $table = $this->resource->getTableName(self::TABLE);
            if (!$connection->isTableExists($table)) {
                return;
            }

            $ids = $connection->fetchCol(
                $connection->select()
                    ->from($table, ['post_id'])
                    ->where('status = ?', PostInterface::STATUS_SCHEDULED)
                    ->where('published_at <= ?', $connection->fetchOne('SELECT NOW()'))
                    ->order('published_at ASC')
            );

            $published = 0;
            foreach ($ids as $id) {
                try {
                    $post = $this->postRepository->getById((int) $id);
                    if ($post->getStatus() !== PostInterface::STATUS_SCHEDULED) {
                        continue;
                    }
                    $post->setStatus(PostInterface::STATUS_PUBLISHED);
                    $this->postRepository->save($post);
                    $published++;
                } catch (\Throwable $e) {
                    $this->logger->warning(sprintf(
                        '[PanthBlog PublishScheduled] post %d failed: %s',
                        (int) $id,
                        $e->getMessage()
                    ));
                }
            }

            if ($published > 0) {
                $this->logger->info(sprintf(
                    '[PanthBlog PublishScheduled] published %d post(s)',
                    $published
                ));
            }
        } catch (\Throwable $e) {
            $this->logger->warning('[PanthBlog PublishScheduled] ' . $e->getMessage());
        }
    }
}
