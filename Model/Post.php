<?php
declare(strict_types=1);

namespace Panth\Blog\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use Panth\Blog\Api\Data\PostInterface;

class Post extends AbstractModel implements PostInterface, IdentityInterface
{
    public const CACHE_TAG = 'panth_blog_post';

    protected $_cacheTag = self::CACHE_TAG;

    protected $_eventPrefix = 'panth_blog_post';

    protected $_eventObject = 'post';

    protected function _construct(): void
    {
        $this->_init(\Panth\Blog\Model\ResourceModel\Post::class);
    }

    public function getIdentities(): array
    {
        return [self::CACHE_TAG . '_' . (int) $this->getId(), self::CACHE_TAG];
    }

    public function hasPublished(): bool
    {
        return $this->getStatus() === self::STATUS_PUBLISHED;
    }

    public function isDraft(): bool
    {
        return $this->getStatus() === self::STATUS_DRAFT;
    }

    public function isScheduled(): bool
    {
        return $this->getStatus() === self::STATUS_SCHEDULED;
    }

    public function isArchived(): bool
    {
        return $this->getStatus() === self::STATUS_ARCHIVED;
    }

    public function getPostId(): ?int
    {
        $id = $this->getData(self::POST_ID);
        return $id === null ? null : (int) $id;
    }

    public function getUrlKey(): string
    {
        return (string) $this->getData(self::URL_KEY);
    }

    public function getTitle(): string
    {
        return (string) $this->getData(self::TITLE);
    }

    public function getMetaTitle(): ?string
    {
        $v = $this->getData(self::META_TITLE);
        return $v === null ? null : (string) $v;
    }

    public function getMetaDescription(): ?string
    {
        $v = $this->getData(self::META_DESCRIPTION);
        return $v === null ? null : (string) $v;
    }

    public function getMetaKeywords(): ?string
    {
        $v = $this->getData(self::META_KEYWORDS);
        return $v === null ? null : (string) $v;
    }

    public function getMetaRobots(): string
    {
        return (string) ($this->getData(self::META_ROBOTS) ?? 'index,follow');
    }

    public function getCanonicalUrl(): ?string
    {
        $v = $this->getData(self::CANONICAL_URL);
        return $v === null ? null : (string) $v;
    }

    public function getShortDescription(): ?string
    {
        $v = $this->getData(self::SHORT_DESCRIPTION);
        return $v === null ? null : (string) $v;
    }

    public function getContent(): ?string
    {
        $v = $this->getData(self::CONTENT);
        return $v === null ? null : (string) $v;
    }

    public function getFeaturedImage(): ?string
    {
        $v = $this->getData(self::FEATURED_IMAGE);
        return $v === null ? null : (string) $v;
    }

    public function getFeaturedImageAlt(): ?string
    {
        $v = $this->getData(self::FEATURED_IMAGE_ALT);
        return $v === null ? null : (string) $v;
    }

    public function getOgImage(): ?string
    {
        $v = $this->getData(self::OG_IMAGE);
        return $v === null ? null : (string) $v;
    }

    public function getAuthorId(): ?int
    {
        $v = $this->getData(self::AUTHOR_ID);
        return ($v === null || $v === '') ? null : (int) $v;
    }

    public function getStatus(): string
    {
        return (string) ($this->getData(self::STATUS) ?? self::STATUS_DRAFT);
    }

    public function getPublishedAt(): ?string
    {
        $v = $this->getData(self::PUBLISHED_AT);
        return $v === null ? null : (string) $v;
    }

    public function getCreatedAt(): ?string
    {
        $v = $this->getData(self::CREATED_AT);
        return $v === null ? null : (string) $v;
    }

    public function getUpdatedAt(): ?string
    {
        $v = $this->getData(self::UPDATED_AT);
        return $v === null ? null : (string) $v;
    }

    public function getIsFeatured(): int
    {
        return (int) $this->getData(self::IS_FEATURED);
    }

    public function getReadingTimeMin(): ?int
    {
        $v = $this->getData(self::READING_TIME_MIN);
        return ($v === null || $v === '') ? null : (int) $v;
    }

    public function getWordCount(): ?int
    {
        $v = $this->getData(self::WORD_COUNT);
        return ($v === null || $v === '') ? null : (int) $v;
    }

    public function getViewCount(): int
    {
        return (int) $this->getData(self::VIEW_COUNT);
    }

    public function getLikeCount(): int
    {
        return (int) $this->getData(self::LIKE_COUNT);
    }

    public function getLayoutTemplate(): string
    {
        return (string) ($this->getData(self::LAYOUT_TEMPLATE) ?? 'default');
    }

    public function getEnableComments(): int
    {
        return (int) $this->getData(self::ENABLE_COMMENTS);
    }

    public function getEnableToc(): int
    {
        return (int) $this->getData(self::ENABLE_TOC);
    }

    public function getTldrSummary(): ?string
    {
        $v = $this->getData(self::TLDR_SUMMARY);
        return $v === null ? null : (string) $v;
    }

    public function getCitationList(): ?string
    {
        $v = $this->getData(self::CITATION_LIST);
        return $v === null ? null : (string) $v;
    }

    public function getSortOrder(): int
    {
        return (int) $this->getData(self::SORT_ORDER);
    }

    public function setPostId(int $id): self
    {
        $this->setData(self::POST_ID, $id);
        return $this;
    }

    public function setUrlKey(string $urlKey): self
    {
        $this->setData(self::URL_KEY, $urlKey);
        return $this;
    }

    public function setTitle(string $title): self
    {
        $this->setData(self::TITLE, $title);
        return $this;
    }

    public function setMetaTitle(?string $metaTitle): self
    {
        $this->setData(self::META_TITLE, $metaTitle);
        return $this;
    }

    public function setMetaDescription(?string $metaDescription): self
    {
        $this->setData(self::META_DESCRIPTION, $metaDescription);
        return $this;
    }

    public function setMetaKeywords(?string $metaKeywords): self
    {
        $this->setData(self::META_KEYWORDS, $metaKeywords);
        return $this;
    }

    public function setMetaRobots(string $metaRobots): self
    {
        $this->setData(self::META_ROBOTS, $metaRobots);
        return $this;
    }

    public function setCanonicalUrl(?string $canonicalUrl): self
    {
        $this->setData(self::CANONICAL_URL, $canonicalUrl);
        return $this;
    }

    public function setShortDescription(?string $shortDescription): self
    {
        $this->setData(self::SHORT_DESCRIPTION, $shortDescription);
        return $this;
    }

    public function setContent(?string $content): self
    {
        $this->setData(self::CONTENT, $content);
        return $this;
    }

    public function setFeaturedImage(?string $featuredImage): self
    {
        $this->setData(self::FEATURED_IMAGE, $featuredImage);
        return $this;
    }

    public function setFeaturedImageAlt(?string $featuredImageAlt): self
    {
        $this->setData(self::FEATURED_IMAGE_ALT, $featuredImageAlt);
        return $this;
    }

    public function setOgImage(?string $ogImage): self
    {
        $this->setData(self::OG_IMAGE, $ogImage);
        return $this;
    }

    public function setAuthorId(?int $authorId): self
    {
        $this->setData(self::AUTHOR_ID, $authorId);
        return $this;
    }

    public function setStatus(string $status): self
    {
        $this->setData(self::STATUS, $status);
        return $this;
    }

    public function setPublishedAt(?string $publishedAt): self
    {
        $this->setData(self::PUBLISHED_AT, $publishedAt);
        return $this;
    }

    public function setCreatedAt(?string $createdAt): self
    {
        $this->setData(self::CREATED_AT, $createdAt);
        return $this;
    }

    public function setUpdatedAt(?string $updatedAt): self
    {
        $this->setData(self::UPDATED_AT, $updatedAt);
        return $this;
    }

    public function setIsFeatured(int $isFeatured): self
    {
        $this->setData(self::IS_FEATURED, $isFeatured);
        return $this;
    }

    public function setReadingTimeMin(?int $readingTimeMin): self
    {
        $this->setData(self::READING_TIME_MIN, $readingTimeMin);
        return $this;
    }

    public function setWordCount(?int $wordCount): self
    {
        $this->setData(self::WORD_COUNT, $wordCount);
        return $this;
    }

    public function setViewCount(int $viewCount): self
    {
        $this->setData(self::VIEW_COUNT, $viewCount);
        return $this;
    }

    public function setLikeCount(int $likeCount): self
    {
        $this->setData(self::LIKE_COUNT, $likeCount);
        return $this;
    }

    public function setLayoutTemplate(string $layoutTemplate): self
    {
        $this->setData(self::LAYOUT_TEMPLATE, $layoutTemplate);
        return $this;
    }

    public function setEnableComments(int $enableComments): self
    {
        $this->setData(self::ENABLE_COMMENTS, $enableComments);
        return $this;
    }

    public function setEnableToc(int $enableToc): self
    {
        $this->setData(self::ENABLE_TOC, $enableToc);
        return $this;
    }

    public function setTldrSummary(?string $tldrSummary): self
    {
        $this->setData(self::TLDR_SUMMARY, $tldrSummary);
        return $this;
    }

    public function setCitationList(?string $citationList): self
    {
        $this->setData(self::CITATION_LIST, $citationList);
        return $this;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->setData(self::SORT_ORDER, $sortOrder);
        return $this;
    }
}
