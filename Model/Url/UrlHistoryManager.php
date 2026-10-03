<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Url;

use Magento\Framework\App\ResourceConnection;

class UrlHistoryManager
{
    private const TABLE = 'panth_blog_url_history';

    private const ENTITY_TYPES = ['post', 'category', 'tag', 'author'];

    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    public function recordSlugChange(
        string $entityType,
        int $entityId,
        string $oldKey,
        string $newKey
    ): void {
        if ($oldKey === '' || $newKey === '' || $oldKey === $newKey) {
            return;
        }
        if (!in_array($entityType, self::ENTITY_TYPES, true)) {
            return;
        }
        if ($entityId <= 0) {
            return;
        }

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        if (!$connection->isTableExists($table)) {
            return;
        }

        $connection->insert($table, [
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'old_url_key' => $oldKey,
            'new_url_key' => $newKey,
        ]);
    }

    public function findCurrent(string $entityType, string $oldUrlKey): ?array
    {
        if ($oldUrlKey === '' || !in_array($entityType, self::ENTITY_TYPES, true)) {
            return null;
        }

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        if (!$connection->isTableExists($table)) {
            return null;
        }

        $select = $connection->select()
            ->from($table, ['entity_id', 'new_url_key'])
            ->where('entity_type = ?', $entityType)
            ->where('old_url_key = ?', $oldUrlKey)
            ->order('redirected_at DESC')
            ->order('id DESC')
            ->limit(1);

        $row = $connection->fetchRow($select);
        if (!$row) {
            return null;
        }

        return [
            'entity_id'   => (int) $row['entity_id'],
            'new_url_key' => (string) $row['new_url_key'],
        ];
    }
}
