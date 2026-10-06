<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Url;

use Magento\Framework\UrlInterface;
use Panth\Blog\Api\Data\AuthorInterface;
use Panth\Blog\Api\Data\CategoryInterface;
use Panth\Blog\Api\Data\TagInterface;
use Panth\Blog\Helper\Config;

class FeedUrlBuilder
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

    public function getMainRssUrl(): string
    {
        return $this->url->getDirectUrl($this->frontName() . '/feed.xml');
    }

    public function getMainAtomUrl(): string
    {
        return $this->url->getDirectUrl($this->frontName() . '/feed/atom.xml');
    }

    public function getCategoryRssUrl(CategoryInterface $cat): string
    {
        return $this->url->getDirectUrl(
            $this->frontName() . '/feed/category/' . (string) $cat->getUrlKey() . '.xml'
        );
    }

    public function getTagRssUrl(TagInterface $tag): string
    {
        return $this->url->getDirectUrl(
            $this->frontName() . '/feed/tag/' . (string) $tag->getUrlKey() . '.xml'
        );
    }

    public function getAuthorRssUrl(AuthorInterface $author): string
    {
        return $this->url->getDirectUrl(
            $this->frontName() . '/feed/author/' . (string) $author->getUrlKey() . '.xml'
        );
    }
}
