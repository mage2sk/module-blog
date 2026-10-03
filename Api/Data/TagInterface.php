<?php
declare(strict_types=1);

namespace Panth\Blog\Api\Data;

/** Panth_Blog tag entity data interface. */
interface TagInterface
{
    public const TAG_ID = 'tag_id';
    public const URL_KEY = 'url_key';
    public const NAME = 'name';
    public const DESCRIPTION = 'description';
    public const META_ROBOTS = 'meta_robots';
    public const POST_COUNT = 'post_count';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * Get tag ID.
     *
     * @return int|null
     */
    public function getTagId(): ?int;

    /**
     * Set tag ID.
     *
     * @param int $id
     * @return $this
     */
    public function setTagId(int $id): self;

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
