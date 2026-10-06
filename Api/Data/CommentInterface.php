<?php
declare(strict_types=1);

namespace Panth\Blog\Api\Data;

/** Panth_Blog comment entity data interface. */
interface CommentInterface
{
    public const COMMENT_ID = 'comment_id';
    public const POST_ID = 'post_id';
    public const PARENT_ID = 'parent_id';
    public const AUTHOR_NAME = 'author_name';
    public const AUTHOR_EMAIL = 'author_email';
    public const AUTHOR_WEBSITE = 'author_website';
    public const CONTENT = 'content';
    public const STATUS = 'status';
    public const IP = 'ip';
    public const USER_AGENT = 'user_agent';
    public const CREATED_AT = 'created_at';
    public const APPROVED_AT = 'approved_at';
    public const VOTES_UP = 'votes_up';
    public const VOTES_DOWN = 'votes_down';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_SPAM = 'spam';
    public const STATUS_TRASH = 'trash';

    /**
     * Get comment ID.
     *
     * @return int|null
     */
    public function getCommentId(): ?int;

    /**
     * Set comment ID.
     *
     * @param int $id
     * @return $this
     */
    public function setCommentId(int $id): self;

    /**
     * Get post ID.
     *
     * @return int
     */
    public function getPostId(): int;

    /**
     * Set post ID.
     *
     * @param int $postId
     * @return $this
     */
    public function setPostId(int $postId): self;

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
     * Get author name.
     *
     * @return string
     */
    public function getAuthorName(): string;

    /**
     * Set author name.
     *
     * @param string $authorName
     * @return $this
     */
    public function setAuthorName(string $authorName): self;

    /**
     * Get author email.
     *
     * @return string|null
     */
    public function getAuthorEmail(): ?string;

    /**
     * Set author email.
     *
     * @param string|null $authorEmail
     * @return $this
     */
    public function setAuthorEmail(?string $authorEmail): self;

    /**
     * Get author website.
     *
     * @return string|null
     */
    public function getAuthorWebsite(): ?string;

    /**
     * Set author website.
     *
     * @param string|null $authorWebsite
     * @return $this
     */
    public function setAuthorWebsite(?string $authorWebsite): self;

    /**
     * Get content.
     *
     * @return string
     */
    public function getContent(): string;

    /**
     * Set content.
     *
     * @param string $content
     * @return $this
     */
    public function setContent(string $content): self;

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
     * Get IP.
     *
     * @return string|null
     */
    public function getIp(): ?string;

    /**
     * Set IP.
     *
     * @param string|null $ip
     * @return $this
     */
    public function setIp(?string $ip): self;

    /**
     * Get user agent.
     *
     * @return string|null
     */
    public function getUserAgent(): ?string;

    /**
     * Set user agent.
     *
     * @param string|null $userAgent
     * @return $this
     */
    public function setUserAgent(?string $userAgent): self;

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
     * Get approved at.
     *
     * @return string|null
     */
    public function getApprovedAt(): ?string;

    /**
     * Set approved at.
     *
     * @param string|null $approvedAt
     * @return $this
     */
    public function setApprovedAt(?string $approvedAt): self;

    /**
     * Get votes up.
     *
     * @return int
     */
    public function getVotesUp(): int;

    /**
     * Set votes up.
     *
     * @param int $votesUp
     * @return $this
     */
    public function setVotesUp(int $votesUp): self;

    /**
     * Get votes down.
     *
     * @return int
     */
    public function getVotesDown(): int;

    /**
     * Set votes down.
     *
     * @param int $votesDown
     * @return $this
     */
    public function setVotesDown(int $votesDown): self;
}
