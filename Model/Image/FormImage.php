<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Image;

use Magento\Catalog\Model\ImageUploader;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class FormImage
{
    private const BASE_PATH = 'blog';

    public function __construct(
        private readonly ImageUploader $imageUploader,
        private readonly StoreManagerInterface $storeManager,
        private readonly Filesystem $filesystem,
        private readonly LoggerInterface $logger
    ) {
    }

    public function toFormData(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->toFormValue($data[$field]);
            }
        }
        return $data;
    }

    public function toFormValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $path = trim((string) $value);
        if ($path === '') {
            return [];
        }
        if (preg_match('#^https?://#i', $path) === 1) {
            $name = basename((string) (parse_url($path, PHP_URL_PATH) ?: $path));
            return [['name' => $name, 'file' => $path, 'url' => $path, 'type' => 'image']];
        }
        $relative = self::BASE_PATH . '/' . ltrim($path, '/');
        $item = [
            'name' => basename($path),
            'file' => $path,
            'url' => $this->getMediaBaseUrl() . $relative,
            'type' => 'image',
        ];
        try {
            $media = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
            if ($media->isFile($relative)) {
                $stat = $media->stat($relative);
                $item['size'] = (int) ($stat['size'] ?? 0);
            }
        } catch (\Throwable $e) {
            $this->logger->info('[Panth_Blog] FormImage stat failed: ' . $e->getMessage());
        }
        return [$item];
    }

    public function applyToPostData(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            $data[$field] = $this->fromFormValue($data[$field] ?? null);
        }
        return $data;
    }

    public function fromFormValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            $value = trim($value);
            return $value === '' ? null : $value;
        }
        if (!is_array($value)) {
            return null;
        }
        $first = reset($value);
        if (!is_array($first)) {
            return null;
        }
        if (isset($first['tmp_name'])) {
            $name = basename((string) ($first['file'] ?? $first['name'] ?? ''));
            if ($name === '' || $name === '.' || $name === '..') {
                return null;
            }
            $relative = (string) $this->imageUploader->moveFileFromTmp($name, true);
            $prefix = self::BASE_PATH . '/';
            return str_starts_with($relative, $prefix) ? substr($relative, strlen($prefix)) : ltrim($relative, '/');
        }
        $file = trim((string) ($first['file'] ?? $first['name'] ?? ''));
        return ($file === '' || str_contains($file, '..')) ? null : $file;
    }

    private function getMediaBaseUrl(): string
    {
        try {
            return rtrim((string) $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/') . '/';
        } catch (\Throwable) {
            return '/media/';
        }
    }
}
