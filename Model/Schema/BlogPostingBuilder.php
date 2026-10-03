<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Schema;

use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Api\Data\AuthorInterface;
use Panth\Blog\Api\Data\CategoryInterface;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Model\Url\AuthorUrlBuilder;
use Panth\Blog\Model\Url\CategoryUrlBuilder;
use Panth\Blog\Model\Url\PostUrlBuilder;

class BlogPostingBuilder
{
    public function __construct(
        private readonly PostUrlBuilder $postUrl,
        private readonly AuthorUrlBuilder $authorUrl,
        private readonly CategoryUrlBuilder $categoryUrl,
        private readonly PersonBuilder $personBuilder,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function build(
        PostInterface $post,
        ?AuthorInterface $author = null,
        ?CategoryInterface $primaryCategory = null,
        array $tags = []
    ): array {
        $canonical = $this->postUrl->getPostUrl($post);
        $shortDescription = $post->getShortDescription();
        if ($shortDescription === null || $shortDescription === '') {
            $shortDescription = substr(strip_tags($post->getContent() ?? ''), 0, 160);
        }

        $images = array_map(
            fn ($image) => $this->mediaUrl((string) $image),
            array_values(array_filter([$post->getFeaturedImage(), $post->getOgImage()]))
        );

        $keywords = '';
        if (!empty($tags)) {
            $keywords = implode(', ', array_map(static fn ($t) => $t->getName(), $tags));
        }

        return [
            '@type' => 'BlogPosting',
            '@id' => $canonical . '#article',
            'headline' => $post->getTitle(),
            'description' => $shortDescription,
            'image' => $images ?: null,
            'datePublished' => $this->iso((string)$post->getPublishedAt()),
            'dateModified' => $this->iso((string)$post->getUpdatedAt()),
            'wordCount' => (int)$post->getWordCount(),
            'timeRequired' => 'PT' . max(1, (int)$post->getReadingTimeMin()) . 'M',
            'articleSection' => $primaryCategory?->getName(),
            'keywords' => $keywords,
            'author' => $author
                ? ['@id' => $this->authorUrl->getAuthorUrl($author) . '#person']
                : null,
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $canonical,
            ],
            'inLanguage' => 'en-US',
        ];
    }

    private function mediaUrl(string $relative): string
    {
        if (stripos($relative, 'http://') === 0 || stripos($relative, 'https://') === 0) {
            return $relative;
        }
        try {
            $base = (string) $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
            return rtrim($base, '/') . '/blog/' . ltrim($relative, '/');
        } catch (\Throwable $e) {
            return $relative;
        }
    }

    private function iso(string $ts): string
    {
        if ($ts === '') {
            return '';
        }
        try {
            $dt = new \DateTimeImmutable($ts);
            return $dt->format('Y-m-d\TH:i:sP');
        } catch (\Exception $e) {
            return '';
        }
    }
}
