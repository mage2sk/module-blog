<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Url;

use Magento\Framework\UrlInterface;
use Panth\Blog\Api\Data\TagInterface;
use Panth\Blog\Helper\Config;

class TagUrlBuilder
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

    public function getTagUrl(TagInterface $tag): string
    {
        return $this->url->getDirectUrl(
            $this->frontName() . '/tag/' . (string) $tag->getUrlKey()
        );
    }

    public function getPaginatedTagUrl(TagInterface $tag, int $page): string
    {
        $base = $this->frontName() . '/tag/' . (string) $tag->getUrlKey();
        if ($page < 2) {
            return $this->url->getDirectUrl($base);
        }

        return $this->url->getDirectUrl($base . '/page/' . $page);
    }
}
