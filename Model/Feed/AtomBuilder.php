<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Feed;

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

class AtomBuilder
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

    public function buildSiteWideAtom(int $storeId, array $posts, callable $authorResolver): string
    {
        $baseUrl = $this->getStoreBaseUrl($storeId);
        return $this->renderFeed(
            title: $this->config->getIndexTitle($storeId) ?: 'Blog',
            link: rtrim($baseUrl, '/') . '/' . $this->config->getRouteFrontName(),
            description: $this->config->getIndexDescription($storeId),
            selfHref: $this->feedUrl->getMainAtomUrl(),
            id: rtrim($baseUrl, '/') . '/' . $this->config->getRouteFrontName(),
            posts: $posts,
            storeId: $storeId,
            authorResolver: $authorResolver
        );
    }

    public function buildCategoryAtom(
        int $storeId,
        array $posts,
        CategoryInterface $category,
        callable $authorResolver
    ): string {
        $link = $this->categoryUrl->getCategoryUrl($category);
        return $this->renderFeed(
            title: $category->getName(),
            link: $link,
            description: (string) ($category->getDescription() ?? $category->getMetaDescription() ?? ''),
            selfHref: $this->feedUrl->getCategoryRssUrl($category),
            id: $link,
            posts: $posts,
            storeId: $storeId,
            authorResolver: $authorResolver
        );
    }

    public function buildTagAtom(
        int $storeId,
        array $posts,
        TagInterface $tag,
        callable $authorResolver
    ): string {
        $link = $this->tagUrl->getTagUrl($tag);
        return $this->renderFeed(
            title: $tag->getName(),
            link: $link,
            description: (string) ($tag->getDescription() ?? ''),
            selfHref: $this->feedUrl->getTagRssUrl($tag),
            id: $link,
            posts: $posts,
            storeId: $storeId,
            authorResolver: $authorResolver
        );
    }

    public function buildAuthorAtom(
        int $storeId,
        array $posts,
        AuthorInterface $author,
        callable $authorResolver
    ): string {
        $link = $this->authorUrl->getAuthorUrl($author);
        return $this->renderFeed(
            title: $author->getDisplayName(),
            link: $link,
            description: (string) ($author->getShortBio() ?? ''),
            selfHref: $this->feedUrl->getAuthorRssUrl($author),
            id: $link,
            posts: $posts,
            storeId: $storeId,
            authorResolver: $authorResolver
        );
    }

    public function iso8601(string $datetime): string
    {
        $ts = strtotime($datetime);
        if ($ts === false || $ts <= 0) {
            $ts = time();
        }
        return gmdate('Y-m-d\TH:i:s\Z', $ts);
    }

    private function renderFeed(
        string $title,
        string $link,
        string $description,
        string $selfHref,
        string $id,
        array $posts,
        int $storeId,
        callable $authorResolver
    ): string {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $feed = $doc->createElementNS('http://www.w3.org/2005/Atom', 'feed');
        $doc->appendChild($feed);

        $feed->appendChild($doc->createElement('id', htmlspecialchars($id, ENT_XML1)));
        $feed->appendChild($doc->createElement('title', htmlspecialchars($title, ENT_XML1)));

        if ($description !== '') {
            $sub = $doc->createElement('subtitle');
            $sub->appendChild($doc->createCDATASection($description));
            $feed->appendChild($sub);
        }

        $feed->appendChild($doc->createElement('updated', $this->iso8601(date('c'))));
        $feed->appendChild($doc->createElement('generator', 'Panth_Blog'));

        $altLink = $doc->createElement('link');
        $altLink->setAttribute('href', $link);
        $altLink->setAttribute('rel', 'alternate');
        $altLink->setAttribute('type', 'text/html');
        $feed->appendChild($altLink);

        $selfLink = $doc->createElement('link');
        $selfLink->setAttribute('href', $selfHref);
        $selfLink->setAttribute('rel', 'self');
        $selfLink->setAttribute('type', 'application/atom+xml');
        $feed->appendChild($selfLink);

        $fullContent = $this->config->isFeedFullContent($storeId);

        foreach ($posts as $post) {
            $entry = $doc->createElement('entry');

            $postUrl = $this->postUrl->getPostUrl($post);

            $entry->appendChild($doc->createElement('id', htmlspecialchars($postUrl, ENT_XML1)));
            $entry->appendChild($doc->createElement(
                'title',
                htmlspecialchars((string) $post->getTitle(), ENT_XML1)
            ));

            $entryLink = $doc->createElement('link');
            $entryLink->setAttribute('href', $postUrl);
            $entryLink->setAttribute('rel', 'alternate');
            $entry->appendChild($entryLink);

            $updatedAt = (string) ($post->getUpdatedAt() ?? $post->getPublishedAt() ?? '');
            $publishedAt = (string) ($post->getPublishedAt() ?? $post->getCreatedAt() ?? '');
            $entry->appendChild($doc->createElement('updated', $this->iso8601($updatedAt)));
            $entry->appendChild($doc->createElement('published', $this->iso8601($publishedAt)));

            $author = $authorResolver($post);
            if ($author !== null && $author->getDisplayName() !== '') {
                $authorEl = $doc->createElement('author');
                $name = $doc->createElement('name');
                $name->appendChild($doc->createCDATASection($author->getDisplayName()));
                $authorEl->appendChild($name);
                $entry->appendChild($authorEl);
            }

            $summary = $this->buildSummary($post);
            if ($summary !== '') {
                $sumNode = $doc->createElement('summary');
                $sumNode->appendChild($doc->createCDATASection($summary));
                $entry->appendChild($sumNode);
            }

            if ($fullContent) {
                $contentNode = $doc->createElement('content');
                $contentNode->setAttribute('type', 'html');
                $contentNode->appendChild($doc->createCDATASection((string) ($post->getContent() ?? '')));
                $entry->appendChild($contentNode);
            }

            $feed->appendChild($entry);
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

    private function getStoreBaseUrl(int $storeId): string
    {
        try {
            return (string) $this->storeManager->getStore($storeId)->getBaseUrl();
        } catch (\Throwable) {
            return '/';
        }
    }
}
