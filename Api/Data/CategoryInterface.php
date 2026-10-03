<?php
declare(strict_types=1);

namespace Panth\Blog\Api\Data;

/** Panth_Blog category entity data interface. */
interface CategoryInterface
{
    public const CATEGORY_ID = 'category_id';
    public const PARENT_ID = 'parent_id';
    public const PATH = 'path';
    public const LEVEL = 'level';
    public const URL_KEY = 'url_key';
    public const NAME = 'name';
    public const DESCRIPTION = 'description';
    public const IMAGE = 'image';
    public const META_TITLE = 'meta_title';
    public const META_DESCRIPTION = 'meta_description';
    public const META_ROBOTS = 'meta_robots';
    public const SORT_ORDER = 'sort_order';
    public const IS_ACTIVE = 'is_active';
    public const POST_COUNT = 'post_count';
    public const POSTS_PER_PAGE = 'posts_per_page';
    public const TEMPLATE = 'template';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * Get category ID.
     *
     * @return int|null
     */
    public function getCategoryId(): ?int;

    /**
     * Set category ID.
     *
     * @param int $id
     * @return $this
     */
    public function setCategoryId(int $id): self;

    /**
     * Get parent ID.
     *
     * @return int|null
     */
    public function getParentId(): ?int;

    /**
     * Set parent ID.
     *
     * @param int|null $parentId
     * @return $this
     */
    public function setParentId(?int $parentId): self;

    /**
     * Get path.
     *
     * @return string|null
     */
    public function getPath(): ?string;

    /**
     * Set path.
     *
     * @param string|null $path
     * @return $this
     */
    public function setPath(?string $path): self;

    /**
     * Get level.
     *
     * @return int
     */
    public function getLevel(): int;

    /**
     * Set level.
     *
     * @param int $level
     * @return $this
     */
    public function setLevel(int $level): self;

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
     * Get name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Set name.
     *
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self;

    /**
     * Get description.
     *
     * @return string|null
     */
    public function getDescription(): ?string;

    /**
     * Set description.
     *
     * @param string|null $description
     * @return $this
     */
    public function setDescription(?string $description): self;

    /**
     * Get image.
     *
     * @return string|null
     */
    public function getImage(): ?string;

    /**
     * Set image.
     *
     * @param string|null $image
     * @return $this
     */
    public function setImage(?string $image): self;

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

    /**
     * Get is active.
     *
     * @return int
     */
    public function getIsActive(): int;

    /**
     * Set is active.
     *
     * @param int $isActive
     * @return $this
     */
    public function setIsActive(int $isActive): self;

    /**
     * Get post count.
     *
     * @return int
     */
    public function getPostCount(): int;

    /**
     * Set post count.
     *
     * @param int $postCount
     * @return $this
     */
    public function setPostCount(int $postCount): self;

    /**
     * Get posts per page.
     *
     * @return int|null
     */
    public function getPostsPerPage(): ?int;

    /**
     * Set posts per page.
     *
     * @param int|null $postsPerPage
     * @return $this
     */
    public function setPostsPerPage(?int $postsPerPage): self;

    /**
     * Get template.
     *
     * @return string
     */
    public function getTemplate(): string;

    /**
     * Set template.
     *
     * @param string $template
     * @return $this
     */
    public function setTemplate(string $template): self;

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
}
