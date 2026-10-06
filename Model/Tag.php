<?php
declare(strict_types=1);

namespace Panth\Blog\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use Panth\Blog\Api\Data\TagInterface;

class Tag extends AbstractModel implements TagInterface, IdentityInterface
{
    public const CACHE_TAG = 'panth_blog_tag';

    protected $_cacheTag = self::CACHE_TAG;

    protected $_eventPrefix = 'panth_blog_tag';

    protected $_eventObject = 'tag';

    protected function _construct(): void
    {
        $this->_init(\Panth\Blog\Model\ResourceModel\Tag::class);
    }

    public function getIdentities(): array
    {
        return [self::CACHE_TAG . '_' . (int) $this->getId(), self::CACHE_TAG];
    }

    public function getTagId(): ?int
    {
        $id = $this->getData(self::TAG_ID);
        return $id === null ? null : (int) $id;
    }

    public function setTagId(int $id): self
    {
        $this->setData(self::TAG_ID, $id);
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

    public function getName(): string
    {
        return (string) $this->getData(self::NAME);
    }

    public function setName(string $name): self
    {
        $this->setData(self::NAME, $name);
        return $this;
    }

    public function getDescription(): ?string
    {
        $v = $this->getData(self::DESCRIPTION);
        return $v === null ? null : (string) $v;
    }

    public function setDescription(?string $description): self
    {
        $this->setData(self::DESCRIPTION, $description);
        return $this;
    }

    public function getMetaRobots(): string
    {
        return (string) ($this->getData(self::META_ROBOTS) ?? 'index,follow');
    }

    public function setMetaRobots(string $metaRobots): self
    {
        $this->setData(self::META_ROBOTS, $metaRobots);
        return $this;
    }

    public function getPostCount(): int
    {
        return (int) $this->getData(self::POST_COUNT);
    }

    public function setPostCount(int $postCount): self
    {
        $this->setData(self::POST_COUNT, $postCount);
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
