<?php
declare(strict_types=1);

namespace Panth\Blog\Setup\Patch\Data;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class InstallDefaultCategories implements DataPatchInterface
{
    private const DEFAULTS = [
        ['url_key' => 'general',   'name' => 'General',   'sort_order' => 10],
        ['url_key' => 'tutorials', 'name' => 'Tutorials', 'sort_order' => 20],
        ['url_key' => 'news',      'name' => 'News',      'sort_order' => 30],
    ];

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
        $table = $this->resource->getTableName('panth_blog_category');
        if (!$conn->isTableExists($table)) {
            return $this;
        }

        foreach (self::DEFAULTS as $row) {
            try {
                $conn->query(
                    'INSERT IGNORE INTO ' . $conn->quoteIdentifier($table)
                    . ' (url_key, name, level, sort_order, is_active, post_count) VALUES (?, ?, 1, ?, 1, 0)',
                    [$row['url_key'], $row['name'], $row['sort_order']]
                );
            } catch (\Throwable) {
            }
        }

        $conn->query(
            'UPDATE ' . $conn->quoteIdentifier($table)
            . ' SET path = CAST(category_id AS CHAR) WHERE (path IS NULL OR path = "") AND level = 1'
        );

        return $this;
    }
}
