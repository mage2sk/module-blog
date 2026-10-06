<?php
declare(strict_types=1);

namespace Panth\Blog\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use Panth\Blog\Api\Data\CommentInterface;

class Comment extends AbstractModel implements CommentInterface, IdentityInterface
{
    public const CACHE_TAG = 'panth_blog_comment';

    public const STATUS_PENDING = CommentInterface::STATUS_PENDING;
    public const STATUS_APPROVED = CommentInterface::STATUS_APPROVED;
    public const STATUS_SPAM = CommentInterface::STATUS_SPAM;
    public const STATUS_TRASH = CommentInterface::STATUS_TRASH;

    protected $_cacheTag = self::CACHE_TAG;

    protected $_eventPrefix = 'panth_blog_comment';

    protected $_eventObject = 'comment';

    protected function _construct(): void
    {
        $this->_init(\Panth\Blog\Model\ResourceModel\Comment::class);
    }

    public function getIdentities(): array
    {
        return [self::CACHE_TAG . '_' . (int) $this->getId(), self::CACHE_TAG];
    }

    public function beforeSave()
    {
        if ($this->getData(self::STATUS) === self::STATUS_APPROVED && !$this->getData(self::APPROVED_AT)) {
            $this->setData(self::APPROVED_AT, gmdate('Y-m-d H:i:s'));
        }
        return parent::beforeSave();
    }

    public function isApproved(): bool
    {
        return $this->getStatus() === self::STATUS_APPROVED;
    }

    public function isPending(): bool
    {
        return $this->getStatus() === self::STATUS_PENDING;
    }

    public function getCommentId(): ?int
    {
        $id = $this->getData(self::COMMENT_ID);
        return $id === null ? null : (int) $id;
    }

    public function setCommentId(int $id): self
    {
        $this->setData(self::COMMENT_ID, $id);
        return $this;
    }

    public function getPostId(): int
    {
        return (int) $this->getData(self::POST_ID);
    }

    public function setPostId(int $postId): self
    {
        $this->setData(self::POST_ID, $postId);
        return $this;
    }

    public function getParentId(): ?int
    {
        $v = $this->getData(self::PARENT_ID);
        return ($v === null || $v === '') ? null : (int) $v;
    }

    public function setParentId(?int $parentId): self
    {
        $this->setData(self::PARENT_ID, $parentId);
        return $this;
    }

    public function getAuthorName(): string
    {
        return (string) $this->getData(self::AUTHOR_NAME);
    }

    public function setAuthorName(string $authorName): self
    {
        $this->setData(self::AUTHOR_NAME, $authorName);
        return $this;
    }

    public function getAuthorEmail(): ?string
    {
        $v = $this->getData(self::AUTHOR_EMAIL);
        return $v === null ? null : (string) $v;
    }

    public function setAuthorEmail(?string $authorEmail): self
    {
        $this->setData(self::AUTHOR_EMAIL, $authorEmail);
        return $this;
    }

    public function getAuthorWebsite(): ?string
    {
        $v = $this->getData(self::AUTHOR_WEBSITE);
        return $v === null ? null : (string) $v;
    }

    public function setAuthorWebsite(?string $authorWebsite): self
    {
        $this->setData(self::AUTHOR_WEBSITE, $authorWebsite);
        return $this;
    }

    public function getContent(): string
    {
        return (string) $this->getData(self::CONTENT);
    }

    public function setContent(string $content): self
    {
        $this->setData(self::CONTENT, $content);
        return $this;
    }

    public function getStatus(): string
    {
        return (string) ($this->getData(self::STATUS) ?? self::STATUS_PENDING);
    }

    public function setStatus(string $status): self
    {
        $this->setData(self::STATUS, $status);
        return $this;
    }

    public function getIp(): ?string
    {
        $v = $this->getData(self::IP);
        return $v === null ? null : (string) $v;
    }

    public function setIp(?string $ip): self
    {
        $this->setData(self::IP, $ip);
        return $this;
    }

    public function getUserAgent(): ?string
    {
        $v = $this->getData(self::USER_AGENT);
        return $v === null ? null : (string) $v;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->setData(self::USER_AGENT, $userAgent);
        return $this;
    }

    public function getCreatedAt(): ?string
    {
        $v = $this->getData(self::CREATED_AT);
        return $v === null ? null : (string) $v;
    }

    public function setCreatedAt(?string $createdAt): self
    {
        $this->setData(self::CREATED_AT, $createdAt);
        return $this;
    }

    public function getApprovedAt(): ?string
    {
        $v = $this->getData(self::APPROVED_AT);
        return $v === null ? null : (string) $v;
    }

    public function setApprovedAt(?string $approvedAt): self
    {
        $this->setData(self::APPROVED_AT, $approvedAt);
        return $this;
    }

    public function getVotesUp(): int
    {
        return (int) $this->getData(self::VOTES_UP);
    }

    public function setVotesUp(int $votesUp): self
    {
        $this->setData(self::VOTES_UP, $votesUp);
        return $this;
    }

    public function getVotesDown(): int
    {
        return (int) $this->getData(self::VOTES_DOWN);
    }

    public function setVotesDown(int $votesDown): self
    {
        $this->setData(self::VOTES_DOWN, $votesDown);
        return $this;
    }
}
