<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Url;

use Magento\Framework\UrlInterface;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Helper\Config;

class PostUrlBuilder
{
    private const FRONT_NAME_DEFAULT = 'blog';

    public function __construct(
        private readonly UrlInterface $url,
        private readonly Config $config
    ) {
    }

    private function frontName(): string
    {
        $name = $this->config->getRouteFrontName();
        return $name !== '' ? trim($name, '/') : self::FRONT_NAME_DEFAULT;
    }

    public function getPostUrl(PostInterface $post): string
    {
        $front = $this->frontName();
        $urlKey = (string) $post->getUrlKey();
        if ($urlKey === '') {
            return $this->url->getDirectUrl($front . '/post/view', [
                '_query' => ['id' => (int) $post->getId()],
            ]);
        }

        return $this->url->getDirectUrl($front . '/' . $urlKey);
    }

    public function getPaginatedIndexUrl(int $page): string
    {
        $front = $this->frontName();
        if ($page < 2) {
            return $this->url->getDirectUrl($front);
        }

        return $this->url->getDirectUrl($front . '/page/' . $page);
    }
}
