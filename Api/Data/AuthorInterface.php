<?php
declare(strict_types=1);

namespace Panth\Blog\Api\Data;

/** Panth_Blog author entity data interface. */
interface AuthorInterface
{
    public const AUTHOR_ID = 'author_id';
    public const USER_ID = 'user_id';
    public const URL_KEY = 'url_key';
    public const DISPLAY_NAME = 'display_name';
    public const ROLE = 'role';
    public const SHORT_BIO = 'short_bio';
    public const LONG_BIO = 'long_bio';
    public const AVATAR = 'avatar';
    public const EMAIL = 'email';
    public const LINKS = 'links';
    public const KNOWS_ABOUT = 'knows_about';
    public const ALUMNI_OF = 'alumni_of';
    public const SAME_AS = 'same_as';
    public const IS_ACTIVE = 'is_active';
    public const SORT_ORDER = 'sort_order';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * Get author ID.
     *
     * @return int|null
     */
    public function getAuthorId(): ?int;

    /**
     * Set author ID.
     *
     * @param int $id
     * @return $this
     */
    public function setAuthorId(int $id): self;

    /**
     * Get user ID.
     *
     * @return int|null
     */
    public function getUserId(): ?int;

    /**
     * Set user ID.
     *
     * @param int|null $userId
     * @return $this
     */
    public function setUserId(?int $userId): self;

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
     * Get display name.
     *
     * @return string
     */
    public function getDisplayName(): string;

    /**
     * Set display name.
     *
     * @param string $displayName
     * @return $this
     */
    public function setDisplayName(string $displayName): self;

    /**
     * Get role.
     *
     * @return string|null
     */
    public function getRole(): ?string;

    /**
     * Set role.
     *
     * @param string|null $role
     * @return $this
     */
    public function setRole(?string $role): self;

    /**
     * Get short bio.
     *
     * @return string|null
     */
    public function getShortBio(): ?string;

    /**
     * Set short bio.
     *
     * @param string|null $shortBio
     * @return $this
     */
    public function setShortBio(?string $shortBio): self;

    /**
     * Get long bio.
     *
     * @return string|null
     */
    public function getLongBio(): ?string;

    /**
     * Set long bio.
     *
     * @param string|null $longBio
     * @return $this
     */
    public function setLongBio(?string $longBio): self;

    /**
     * Get avatar.
     *
     * @return string|null
     */
    public function getAvatar(): ?string;

    /**
     * Set avatar.
     *
     * @param string|null $avatar
     * @return $this
     */
    public function setAvatar(?string $avatar): self;

    /**
     * Get email.
     *
     * @return string|null
     */
    public function getEmail(): ?string;

    /**
     * Set email.
     *
     * @param string|null $email
     * @return $this
     */
    public function setEmail(?string $email): self;

    /**
     * Get links.
     *
     * @return string|null
     */
    public function getLinks(): ?string;

    /**
     * Set links.
     *
     * @param string|null $links
     * @return $this
     */
    public function setLinks(?string $links): self;

    /**
     * Get knows about.
     *
     * @return string|null
     */
    public function getKnowsAbout(): ?string;

    /**
     * Set knows about.
     *
     * @param string|null $knowsAbout
     * @return $this
     */
    public function setKnowsAbout(?string $knowsAbout): self;

    /**
     * Get alumni of.
     *
     * @return string|null
     */
    public function getAlumniOf(): ?string;

    /**
     * Set alumni of.
     *
     * @param string|null $alumniOf
     * @return $this
     */
    public function setAlumniOf(?string $alumniOf): self;

    /**
     * Get same as.
     *
     * @return string|null
     */
    public function getSameAs(): ?string;

    /**
     * Set same as.
     *
     * @param string|null $sameAs
     * @return $this
     */
    public function setSameAs(?string $sameAs): self;

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
