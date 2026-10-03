<?php
declare(strict_types=1);

namespace Panth\Blog\Api\Data;

/** Panth_Blog post entity data interface. */
interface PostInterface
{
    public const POST_ID = 'post_id';
    public const URL_KEY = 'url_key';
    public const TITLE = 'title';
    public const META_TITLE = 'meta_title';
    public const META_DESCRIPTION = 'meta_description';
    public const META_KEYWORDS = 'meta_keywords';
    public const META_ROBOTS = 'meta_robots';
    public const CANONICAL_URL = 'canonical_url';
    public const SHORT_DESCRIPTION = 'short_description';
    public const CONTENT = 'content';
    public const FEATURED_IMAGE = 'featured_image';
    public const FEATURED_IMAGE_ALT = 'featured_image_alt';
    public const OG_IMAGE = 'og_image';
    public const AUTHOR_ID = 'author_id';
    public const STATUS = 'status';
    public const PUBLISHED_AT = 'published_at';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';
    public const IS_FEATURED = 'is_featured';
    public const READING_TIME_MIN = 'reading_time_min';
    public const WORD_COUNT = 'word_count';
    public const VIEW_COUNT = 'view_count';
    public const LIKE_COUNT = 'like_count';
    public const LAYOUT_TEMPLATE = 'layout_template';
    public const ENABLE_COMMENTS = 'enable_comments';
    public const ENABLE_TOC = 'enable_toc';
    public const TLDR_SUMMARY = 'tldr_summary';
    public const CITATION_LIST = 'citation_list';
    public const SORT_ORDER = 'sort_order';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    /**
     * Get post ID.
     *
     * @return int|null
     */
    public function getPostId(): ?int;

    /**
     * Set post ID.
     *
     * @param int $id
     * @return $this
     */
    public function setPostId(int $id): self;

    /**
     * Get URL key.
     *
     * @return string
     */
    public function getUrlKey(): string;

    /**
     * Set URL key.
     *
     * @param string $urlKey
     * @return $this
     */
    public function setUrlKey(string $urlKey): self;

    /**
     * Get title.
     *
     * @return string
     */
    public function getTitle(): string;

    /**
     * Set title.
     *
     * @param string $title
     * @return $this
     */
    public function setTitle(string $title): self;

    /**
     * Get meta title.
     *
     * @return string|null
     */
    public function getMetaTitle(): ?string;

    /**
     * Set meta title.
     *
     * @param string|null $metaTitle
     * @return $this
     */
    public function setMetaTitle(?string $metaTitle): self;

    /**
     * Get meta description.
     *
     * @return string|null
     */
    public function getMetaDescription(): ?string;

    /**
     * Set meta description.
     *
     * @param string|null $metaDescription
     * @return $this
     */
    public function setMetaDescription(?string $metaDescription): self;

    /**
     * Get meta keywords.
     *
     * @return string|null
     */
    public function getMetaKeywords(): ?string;

    /**
     * Set meta keywords.
     *
     * @param string|null $metaKeywords
     * @return $this
     */
    public function setMetaKeywords(?string $metaKeywords): self;

    /**
     * Get meta robots.
     *
     * @return string
     */
    public function getMetaRobots(): string;

    /**
     * Set meta robots.
     *
     * @param string $metaRobots
     * @return $this
     */
    public function setMetaRobots(string $metaRobots): self;

    /**
     * Get canonical URL.
     *
     * @return string|null
     */
    public function getCanonicalUrl(): ?string;

    /**
     * Set canonical URL.
     *
     * @param string|null $canonicalUrl
     * @return $this
     */
    public function setCanonicalUrl(?string $canonicalUrl): self;

    /**
     * Get short description.
     *
     * @return string|null
     */
    public function getShortDescription(): ?string;

    /**
     * Set short description.
     *
     * @param string|null $shortDescription
     * @return $this
     */
    public function setShortDescription(?string $shortDescription): self;

    /**
     * Get content.
     *
     * @return string|null
     */
    public function getContent(): ?string;

    /**
     * Set content.
     *
     * @param string|null $content
     * @return $this
     */
    public function setContent(?string $content): self;

    /**
     * Get featured image.
     *
     * @return string|null
     */
    public function getFeaturedImage(): ?string;

    /**
     * Set featured image.
     *
     * @param string|null $featuredImage
     * @return $this
     */
    public function setFeaturedImage(?string $featuredImage): self;

    /**
     * Get featured image alt.
     *
     * @return string|null
     */
    public function getFeaturedImageAlt(): ?string;

    /**
     * Set featured image alt.
     *
     * @param string|null $featuredImageAlt
     * @return $this
     */
    public function setFeaturedImageAlt(?string $featuredImageAlt): self;

    /**
     * Get OG image.
     *
     * @return string|null
     */
    public function getOgImage(): ?string;

    /**
     * Set OG image.
     *
     * @param string|null $ogImage
     * @return $this
     */
    public function setOgImage(?string $ogImage): self;

    /**
     * Get author ID.
     *
     * @return int|null
     */
    public function getAuthorId(): ?int;

    /**
     * Set author ID.
     *
     * @param int|null $authorId
     * @return $this
     */
    public function setAuthorId(?int $authorId): self;

    /**
     * Get status.
     *
     * @return string
     */
    public function getStatus(): string;

    /**
     * Set status.
     *
     * @param string $status
     * @return $this
     */
    public function setStatus(string $status): self;

    /**
     * Get published at.
     *
     * @return string|null
     */
    public function getPublishedAt(): ?string;

    /**
     * Set published at.
     *
     * @param string|null $publishedAt
     * @return $this
     */
    public function setPublishedAt(?string $publishedAt): self;

    /**
     * Get created at.
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * Set created at.
     *
     * @param string|null $createdAt
     * @return $this
     */
    public function setCreatedAt(?string $createdAt): self;

    /**
     * Get updated at.
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string;

    /**
     * Set updated at.
     *
     * @param string|null $updatedAt
     * @return $this
     */
    public function setUpdatedAt(?string $updatedAt): self;

    /**
     * Get is featured.
     *
     * @return int
     */
    public function getIsFeatured(): int;

    /**
     * Set is featured.
     *
     * @param int $isFeatured
     * @return $this
     */
    public function setIsFeatured(int $isFeatured): self;

    /**
     * Get reading time min.
     *
     * @return int|null
     */
    public function getReadingTimeMin(): ?int;

    /**
     * Set reading time min.
     *
     * @param int|null $readingTimeMin
     * @return $this
     */
    public function setReadingTimeMin(?int $readingTimeMin): self;

    /**
     * Get word count.
     *
     * @return int|null
     */
    public function getWordCount(): ?int;

    /**
     * Set word count.
     *
     * @param int|null $wordCount
     * @return $this
     */
    public function setWordCount(?int $wordCount): self;

    /**
     * Get view count.
     *
     * @return int
     */
    public function getViewCount(): int;

    /**
     * Set view count.
     *
     * @param int $viewCount
     * @return $this
     */
    public function setViewCount(int $viewCount): self;

    /**
     * Get like count.
     *
     * @return int
     */
    public function getLikeCount(): int;

    /**
     * Set like count.
     *
     * @param int $likeCount
     * @return $this
     */
    public function setLikeCount(int $likeCount): self;

    /**
     * Get layout template.
     *
     * @return string
     */
    public function getLayoutTemplate(): string;

    /**
     * Set layout template.
     *
     * @param string $layoutTemplate
     * @return $this
     */
    public function setLayoutTemplate(string $layoutTemplate): self;

    /**
     * Get enable comments.
     *
     * @return int
     */
    public function getEnableComments(): int;

    /**
     * Set enable comments.
     *
     * @param int $enableComments
     * @return $this
     */
    public function setEnableComments(int $enableComments): self;

    /**
     * Get enable TOC.
     *
     * @return int
     */
    public function getEnableToc(): int;

    /**
     * Set enable TOC.
     *
     * @param int $enableToc
     * @return $this
     */
    public function setEnableToc(int $enableToc): self;

    /**
     * Get TL;DR summary.
     *
     * @return string|null
     */
    public function getTldrSummary(): ?string;

    /**
     * Set TL;DR summary.
     *
     * @param string|null $tldrSummary
     * @return $this
     */
    public function setTldrSummary(?string $tldrSummary): self;

    /**
     * Get citation list.
     *
     * @return string|null
     */
    public function getCitationList(): ?string;

    /**
     * Set citation list.
     *
     * @param string|null $citationList
     * @return $this
     */
    public function setCitationList(?string $citationList): self;

    /**
     * Get sort order.
     *
     * @return int
     */
    public function getSortOrder(): int;

    /**
     * Set sort order.
     *
     * @param int $sortOrder
     * @return $this
     */
    public function setSortOrder(int $sortOrder): self;
}
