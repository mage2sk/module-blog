<?php
declare(strict_types=1);

namespace Panth\Blog\Cron;

use Magento\Framework\App\ResourceConnection;
use Panth\Blog\Helper\Config;
use Psr\Log\LoggerInterface;

class NotifyIndexNow
{
    private const TABLE = 'panth_blog_indexnow_queue';
    private const ENDPOINT = 'https://api.indexnow.org/indexnow';

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        try {
            if (!$this->config->isIndexNowEnabled()) {
                return;
            }

            $connection = $this->resource->getConnection();
            $table = $this->resource->getTableName(self::TABLE);
            if (!$connection->isTableExists($table)) {
                return;
            }

            $batchSize = max(1, $this->config->getIndexNowBatchSize());

            $select = $connection->select()
                ->from($table, ['id', 'url', 'store_id'])
                ->where('status = ?', 'pending')
                ->order('queued_at ASC')
                ->limit($batchSize);

            $rows = $connection->fetchAll($select);
            if ($rows === []) {
                return;
            }

            $apiKey = $this->resolveIndexNowKey();
            if ($apiKey === '') {
                return;
            }

            $sentIds = [];
            $failedIds = [];

            $host = parse_url((string) $rows[0]['url'], PHP_URL_HOST);
            $rows = array_values(array_filter(
                $rows,
                static fn ($r) => parse_url((string) $r['url'], PHP_URL_HOST) === $host
            ));
            $urls = array_values(array_map(static fn ($r) => (string) $r['url'], $rows));
            $ok = $this->postBatch($apiKey, $urls);
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                if ($ok) {
                    $sentIds[] = $id;
                } else {
                    $failedIds[] = $id;
                }
            }

            if ($sentIds !== []) {
                $connection->update(
                    $table,
                    ['status' => 'sent', 'sent_at' => $connection->fetchOne('SELECT NOW()')],
                    ['id IN (?)' => $sentIds]
                );
            }
            if ($failedIds !== []) {
                $connection->update(
                    $table,
                    ['status' => 'failed'],
                    ['id IN (?)' => $failedIds]
                );
            }

            $this->logger->info(sprintf(
                '[PanthBlog NotifyIndexNow] sent=%d failed=%d',
                count($sentIds),
                count($failedIds)
            ));
        } catch (\Throwable $e) {
            $this->logger->warning('[PanthBlog NotifyIndexNow] ' . $e->getMessage());
        }
    }

    private function resolveIndexNowKey(): string
    {
        try {
            $key = (string) $this->config->getValue('indexnow/api_key');
        } catch (\Throwable) {
            $key = '';
        }
        $key = trim($key);
        return preg_match('/^[A-Za-z0-9-]{8,128}$/', $key) === 1 ? $key : '';
    }

    private function postBatch(string $apiKey, array $urls): bool
    {
        if ($urls === [] || !function_exists('curl_init')) {
            return false;
        }

        $host = parse_url($urls[0], PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }

        $payload = [
            'host'        => $host,
            'key'         => $apiKey,
            'keyLocation' => 'https://' . $host . '/' . $apiKey . '.txt',
            'urlList'     => array_values($urls),
        ];

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return false;
        }

        $ch = curl_init(self::ENDPOINT);
        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json; charset=utf-8',
                'User-Agent: Panth-Blog-IndexNow/1.0',
            ],
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $response = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $response !== false && $code >= 200 && $code < 300;
    }
}
