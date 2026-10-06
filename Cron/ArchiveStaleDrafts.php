<?php
declare(strict_types=1);

namespace Panth\Blog\Cron;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class ArchiveStaleDrafts
{
    private const TABLE = 'panth_blog_post';
    private const STALE_DAYS = 180;

    public function __construct(
        private readonly ResourceConnection $resource,
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

            $cutoff = $connection->fetchOne(
                'SELECT DATE_SUB(NOW(), INTERVAL ' . self::STALE_DAYS . ' DAY)'
            );

            $affected = $connection->update(
                $table,
                ['status' => 'archived'],
                [
                    'status = ?'      => 'draft',
                    'updated_at < ?'  => $cutoff,
                ]
            );

            if ($affected > 0) {
                $this->logger->info(sprintf(
                    '[PanthBlog ArchiveStaleDrafts] archived %d draft(s) older than %d days',
                    $affected,
                    self::STALE_DAYS
                ));
            }
        } catch (\Throwable $e) {
            $this->logger->warning('[PanthBlog ArchiveStaleDrafts] ' . $e->getMessage());
        }
    }
}
