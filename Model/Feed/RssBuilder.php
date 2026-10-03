<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Feed;

use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Api\Data\AuthorInterface;
use Panth\Blog\Api\Data\CategoryInterface;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Api\Data\TagInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Url\AuthorUrlBuilder;
use Panth\Blog\Model\Url\CategoryUrlBuilder;
use Panth\Blog\Model\Url\FeedUrlBuilder;
use Panth\Blog\Model\Url\PostUrlBuilder;
use Panth\Blog\Model\Url\TagUrlBuilder;
use Panth\Blog\Model\Text\Truncator;

class RssBuilder
{
    public function __construct(
        private readonly PostUrlBuilder $postUrl,
        private readonly CategoryUrlBuilder $categoryUrl,
        private readonly TagUrlBuilder $tagUrl,
        private readonly AuthorUrlBuilder $authorUrl,
        private readonly FeedUrlBuilder $feedUrl,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly Truncator $truncator
    ) {
    }

    public function buildSiteWideRss(int $storeId, array $posts, callable $authorResolver): string
    {
        $baseUrl = $this->getStoreBaseUrl($storeId);
        return $this->renderChannel(
            title: $this->config->getIndexTitle($storeId) ?: 'Blog',
            link: rtrim($baseUrl, '/') . '/' . $this->config->getRouteFrontName(),
            description: $this->config->getIndexDescription($storeId),
            selfHref: $this->feedUrl->getMainRssUrl(),
            posts: $posts,
            storeId: $storeId,
            authorResolver: $authorResolver
        );
    }

    public function buildCategoryRss(
        int $storeId,
        array $posts,
        CategoryInterface $category,
        callable $authorResolver
    ): string {
        return $this->renderChannel(
            title: $category->getName(),
            link: $this->categoryUrl->getCategoryUrl($category),
            description: (string) ($category->getDescription() ?? $category->getMetaDescription() ?? ''),
            selfHref: $this->feedUrl->getCategoryRssUrl($category),
            posts: $posts,
            storeId: $storeId,
            authorResolver: $authorResolver
        );
    }

    public function buildTagRss(
        int $storeId,
        array $posts,
        TagInterface $tag,
        callable $authorResolver
    ): string {
        return $this->renderChannel(
            title: $tag->getName(),
            link: $this->tagUrl->getTagUrl($tag),
            description: (string) ($tag->getDescription() ?? ''),
            selfHref: $this->feedUrl->getTagRssUrl($tag),
            posts: $posts,
            storeId: $storeId,
            authorResolver: $authorResolver
        );
    }

    public function buildAuthorRss(
        int $storeId,
        array $posts,
        AuthorInterface $author,
        callable $authorResolver
    ): string {
        return $this->renderChannel(
            title: $author->getDisplayName(),
            link: $this->authorUrl->getAuthorUrl($author),
            description: (string) ($author->getShortBio() ?? ''),
            selfHref: $this->feedUrl->getAuthorRssUrl($author),
            posts: $posts,
            storeId: $storeId,
            authorResolver: $authorResolver
        );
    }

    public function rfc822(string $datetime): string
    {
        try {
            $ts = strtotime($datetime);
            if ($ts === false || $ts <= 0) {
                $ts = time();
            }
            return gmdate('D, d M Y H:i:s', $ts) . ' +0000';
        } catch (\Throwable) {
            return gmdate('D, d M Y H:i:s') . ' +0000';
        }
    }

    private function renderChannel(
        string $title,
        string $link,
        string $description,
        string $selfHref,
        array $posts,
        int $storeId,
        callable $authorResolver
    ): string {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $rss = $doc->createElement('rss');
        $rss->setAttribute('version', '2.0');
        $rss->setAttribute('xmlns:content', 'http://purl.org/rss/1.0/modules/content/');
        $rss->setAttribute('xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $rss->setAttribute('xmlns:media', 'http://search.yahoo.com/mrss/');
        $rss->setAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $doc->appendChild($rss);

        $channel = $doc->createElement('channel');
        $rss->appendChild($channel);

        $channel->appendChild($doc->createElement('title', htmlspecialchars($title, ENT_XML1)));
        $channel->appendChild($doc->createElement('link', htmlspecialchars($link, ENT_XML1)));
        $descNode = $doc->createElement('description');
        $descNode->appendChild($doc->createCDATASection($description));
        $channel->appendChild($descNode);
        $channel->appendChild($doc->createElement('language', 'en-US'));
        $channel->appendChild($doc->createElement('lastBuildDate', $this->rfc822(date('c'))));
        $channel->appendChild($doc->createElement('generator', 'Panth_Blog'));

        $atomLink = $doc->createElement('atom:link');
        $atomLink->setAttribute('href', $selfHref);
        $atomLink->setAttribute('rel', 'self');
        $atomLink->setAttribute('type', 'application/rss+xml');
        $channel->appendChild($atomLink);

        $fullContent = $this->config->isFeedFullContent($storeId);

        foreach ($posts as $post) {
            $item = $doc->createElement('item');

            $postUrl = $this->postUrl->getPostUrl($post);

            $item->appendChild($doc->createElement(
                'title',
                htmlspecialchars((string) $post->getTitle(), ENT_XML1)
            ));
            $item->appendChild($doc->createElement('link', htmlspecialchars($postUrl, ENT_XML1)));

            $guid = $doc->createElement('guid', htmlspecialchars($postUrl, ENT_XML1));
            $guid->setAttribute('isPermaLink', 'true');
            $item->appendChild($guid);

            $summary = $this->buildSummary($post);
            $descItem = $doc->createElement('description');
            $descItem->appendChild($doc->createCDATASection($summary));
            $item->appendChild($descItem);

            if ($fullContent) {
                $content = (string) ($post->getContent() ?? '');
                $encoded = $doc->createElement('content:encoded');
                $encoded->appendChild($doc->createCDATASection($content));
                $item->appendChild($encoded);
            }

            $publishedAt = (string) ($post->getPublishedAt() ?? $post->getCreatedAt() ?? '');
            $item->appendChild($doc->createElement('pubDate', $this->rfc822($publishedAt)));

            $author = $authorResolver($post);
            if ($author !== null && $author->getDisplayName() !== '') {
                $creator = $doc->createElement('dc:creator');
                $creator->appendChild($doc->createCDATASection($author->getDisplayName()));
                $item->appendChild($creator);
            }

            $featured = (string) ($post->getFeaturedImage() ?? '');
            if ($featured !== '') {
                $media = $doc->createElement('media:content');
                $media->setAttribute('url', $this->mediaUrl($storeId, $featured));
                $media->setAttribute('medium', 'image');
                $alt = (string) ($post->getFeaturedImageAlt() ?? '');
                if ($alt !== '') {
                    $title = $doc->createElement('media:title', htmlspecialchars($alt, ENT_XML1));
                    $media->appendChild($title);
                }
                $item->appendChild($media);
            }

            $channel->appendChild($item);
        }

        return (string) $doc->saveXML();
    }

    private function buildSummary(PostInterface $post): string
    {
        $short = trim((string) ($post->getShortDescription() ?? ''));
        if ($short !== '') {
            return $short;
        }
        $content = strip_tags((string) ($post->getContent() ?? ''));
        $content = trim(preg_replace('/\s+/u', ' ', $content) ?? '');
        if ($content === '') {
            return '';
        }
        return $this->truncator->truncate($content, 320);
    }

    private function mediaUrl(int $storeId, string $relative): string
    {
        if (stripos($relative, 'http://') === 0 || stripos($relative, 'https://') === 0) {
            return $relative;
        }
        try {
            $store = $this->storeManager->getStore($storeId);
            $base = (string) $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
            return rtrim($base, '/') . '/blog/' . ltrim($relative, '/');
        } catch (\Throwable) {
            return $relative;
        }
    }

    private function getStoreBaseUrl(int $storeId): string
    {
        try {
            return (string) $this->storeManager->getStore($storeId)->getBaseUrl();
        } catch (\Throwable) {
            return '/';
        }
    }
}
