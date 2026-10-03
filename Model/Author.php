<?php
declare(strict_types=1);

namespace Panth\Blog\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use Panth\Blog\Api\Data\AuthorInterface;

class Author extends AbstractModel implements AuthorInterface, IdentityInterface
{
    public const CACHE_TAG = 'panth_blog_author';

    protected $_cacheTag = self::CACHE_TAG;

    protected $_eventPrefix = 'panth_blog_author';

    protected $_eventObject = 'author';

    protected function _construct(): void
    {
        $this->_init(\Panth\Blog\Model\ResourceModel\Author::class);
    }

    public function getIdentities(): array
    {
        return [self::CACHE_TAG . '_' . (int) $this->getId(), self::CACHE_TAG];
    }

    public function getLinksArray(): array
    {
        return $this->decodeJsonField($this->getLinks());
    }

    public function getKnowsAboutArray(): array
    {
        return $this->decodeJsonField($this->getKnowsAbout());
    }

    public function getSameAsArray(): array
    {
        return $this->decodeJsonField($this->getSameAs());
    }

    private function decodeJsonField(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function getAuthorId(): ?int
    {
        $id = $this->getData(self::AUTHOR_ID);
        return $id === null ? null : (int) $id;
    }

    public function setAuthorId(int $id): self
    {
        $this->setData(self::AUTHOR_ID, $id);
        return $this;
    }

    public function getUserId(): ?int
    {
        $v = $this->getData(self::USER_ID);
        return ($v === null || $v === '') ? null : (int) $v;
    }

    public function setUserId(?int $userId): self
    {
        $this->setData(self::USER_ID, $userId);
        return $this;
    }

    public function getUrlKey(): string
    {
        return (string) $this->getData(self::URL_KEY);
    }

    public function setUrlKey(string $urlKey): self
    {
        $this->setData(self::URL_KEY, $urlKey);
        return $this;
    }

    public function getDisplayName(): string
    {
        return (string) $this->getData(self::DISPLAY_NAME);
    }

    public function setDisplayName(string $displayName): self
    {
        $this->setData(self::DISPLAY_NAME, $displayName);
        return $this;
    }

    public function getRole(): ?string
    {
        $v = $this->getData(self::ROLE);
        return $v === null ? null : (string) $v;
    }

    public function setRole(?string $role): self
    {
        $this->setData(self::ROLE, $role);
        return $this;
    }

    public function getShortBio(): ?string
    {
        $v = $this->getData(self::SHORT_BIO);
        return $v === null ? null : (string) $v;
    }

    public function setShortBio(?string $shortBio): self
    {
        $this->setData(self::SHORT_BIO, $shortBio);
        return $this;
    }

    public function getLongBio(): ?string
    {
        $v = $this->getData(self::LONG_BIO);
        return $v === null ? null : (string) $v;
    }

    public function setLongBio(?string $longBio): self
    {
        $this->setData(self::LONG_BIO, $longBio);
        return $this;
    }

    public function getAvatar(): ?string
    {
        $v = $this->getData(self::AVATAR);
        return $v === null ? null : (string) $v;
    }

    public function setAvatar(?string $avatar): self
    {
        $this->setData(self::AVATAR, $avatar);
        return $this;
    }

    public function getEmail(): ?string
    {
        $v = $this->getData(self::EMAIL);
        return $v === null ? null : (string) $v;
    }

    public function setEmail(?string $email): self
    {
        $this->setData(self::EMAIL, $email);
        return $this;
    }

    public function getLinks(): ?string
    {
        $v = $this->getData(self::LINKS);
        return $v === null ? null : (string) $v;
    }

    public function setLinks(?string $links): self
    {
        $this->setData(self::LINKS, $links);
        return $this;
    }

    public function getKnowsAbout(): ?string
    {
        $v = $this->getData(self::KNOWS_ABOUT);
        return $v === null ? null : (string) $v;
    }

    public function setKnowsAbout(?string $knowsAbout): self
    {
        $this->setData(self::KNOWS_ABOUT, $knowsAbout);
        return $this;
    }

    public function getAlumniOf(): ?string
    {
        $v = $this->getData(self::ALUMNI_OF);
        return $v === null ? null : (string) $v;
    }

    public function setAlumniOf(?string $alumniOf): self
    {
        $this->setData(self::ALUMNI_OF, $alumniOf);
        return $this;
    }

    public function getSameAs(): ?string
    {
        $v = $this->getData(self::SAME_AS);
        return $v === null ? null : (string) $v;
    }

    public function setSameAs(?string $sameAs): self
    {
        $this->setData(self::SAME_AS, $sameAs);
        return $this;
    }

    public function getIsActive(): int
    {
        return (int) $this->getData(self::IS_ACTIVE);
    }

    public function setIsActive(int $isActive): self
    {
        $this->setData(self::IS_ACTIVE, $isActive);
        return $this;
    }

    public function getSortOrder(): int
    {
        return (int) $this->getData(self::SORT_ORDER);
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->setData(self::SORT_ORDER, $sortOrder);
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

    public function getUpdatedAt(): ?string
    {
        $v = $this->getData(self::UPDATED_AT);
        return $v === null ? null : (string) $v;
    }

    public function setUpdatedAt(?string $updatedAt): self
    {
        $this->setData(self::UPDATED_AT, $updatedAt);
        return $this;
    }
}
