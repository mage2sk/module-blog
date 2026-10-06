<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Url;

use Magento\Framework\UrlInterface;
use Panth\Blog\Api\Data\AuthorInterface;
use Panth\Blog\Helper\Config;

class AuthorUrlBuilder
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

    public function getAuthorUrl(AuthorInterface $author): string
    {
        return $this->url->getDirectUrl(
            $this->frontName() . '/author/' . (string) $author->getUrlKey()
        );
    }

    public function getPaginatedAuthorUrl(AuthorInterface $author, int $page): string
    {
        $base = $this->frontName() . '/author/' . (string) $author->getUrlKey();
        if ($page < 2) {
            return $this->url->getDirectUrl($base);
        }

        return $this->url->getDirectUrl($base . '/page/' . $page);
    }
}
