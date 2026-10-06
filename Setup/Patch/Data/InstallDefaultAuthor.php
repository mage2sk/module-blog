<?php
declare(strict_types=1);

namespace Panth\Blog\Setup\Patch\Data;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class InstallDefaultAuthor implements DataPatchInterface
{
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }

    public function apply(): self
    {
        $conn = $this->resource->getConnection();
        $table = $this->resource->getTableName('panth_blog_author');
        if (!$conn->isTableExists($table)) {
            return $this;
        }

        try {
            $conn->query(
                'INSERT IGNORE INTO ' . $conn->quoteIdentifier($table)
                . ' (url_key, display_name, role, short_bio, is_active) VALUES (?, ?, ?, ?, 1)',
                ['admin', 'Admin', 'Editor', '']
            );
        } catch (\Throwable) {
        }

        return $this;
    }
}
