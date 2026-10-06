<?php
declare(strict_types=1);

namespace Panth\Blog\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use Panth\Blog\Api\Data\CategoryInterface;

class Category extends AbstractModel implements CategoryInterface, IdentityInterface
{
    public const CACHE_TAG = 'panth_blog_category';

    protected $_cacheTag = self::CACHE_TAG;

    protected $_eventPrefix = 'panth_blog_category';

    protected $_eventObject = 'category';

    protected function _construct(): void
    {
        $this->_init(\Panth\Blog\Model\ResourceModel\Category::class);
    }

    public function getIdentities(): array
    {
        return [self::CACHE_TAG . '_' . (int) $this->getId(), self::CACHE_TAG];
    }

    public function getChildCategoryIds(): array
    {
        $path = (string) $this->getPath();
        $id = (int) $this->getId();
        if ($path === '' || $id <= 0) {
            return [];
        }
        $resource = $this->getResource();
        if (!$resource instanceof \Panth\Blog\Model\ResourceModel\Category) {
            return [];
        }
        $connection = $resource->getConnection();
        $select = $connection->select()
            ->from(['c' => $resource->getMainTable()], ['category_id'])
            ->where('c.path LIKE ?', $path . '/%')
            ->where('c.category_id <> ?', $id);
        $ids = $connection->fetchCol($select);
        return array_map('intval', is_array($ids) ? $ids : []);
    }

    public function getCategoryId(): ?int
    {
        $id = $this->getData(self::CATEGORY_ID);
        return $id === null ? null : (int) $id;
    }

    public function getParentId(): ?int
    {
        $v = $this->getData(self::PARENT_ID);
        return ($v === null || $v === '') ? null : (int) $v;
    }

    public function getPath(): ?string
    {
        $v = $this->getData(self::PATH);
        return $v === null ? null : (string) $v;
    }

    public function getLevel(): int
    {
        return (int) ($this->getData(self::LEVEL) ?? 1);
    }

    public function getUrlKey(): string
    {
        return (string) $this->getData(self::URL_KEY);
    }

    public function getName(): string
    {
        return (string) $this->getData(self::NAME);
    }

    public function getDescription(): ?string
    {
        $v = $this->getData(self::DESCRIPTION);
        return $v === null ? null : (string) $v;
    }

    public function getImage(): ?string
    {
        $v = $this->getData(self::IMAGE);
        return $v === null ? null : (string) $v;
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

    public function getMetaRobots(): string
    {
        return (string) ($this->getData(self::META_ROBOTS) ?? 'index,follow');
    }

    public function getSortOrder(): int
    {
        return (int) $this->getData(self::SORT_ORDER);
    }

    public function getIsActive(): int
    {
        return (int) $this->getData(self::IS_ACTIVE);
    }

    public function getPostCount(): int
    {
        return (int) $this->getData(self::POST_COUNT);
    }

    public function getPostsPerPage(): ?int
    {
        $v = $this->getData(self::POSTS_PER_PAGE);
        return ($v === null || $v === '') ? null : (int) $v;
    }

    public function getTemplate(): string
    {
        return (string) ($this->getData(self::TEMPLATE) ?? 'grid');
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

    public function setCategoryId(int $id): self
    {
        $this->setData(self::CATEGORY_ID, $id);
        return $this;
    }

    public function setParentId(?int $parentId): self
    {
        $this->setData(self::PARENT_ID, $parentId);
        return $this;
    }

    public function setPath(?string $path): self
    {
        $this->setData(self::PATH, $path);
        return $this;
    }

    public function setLevel(int $level): self
    {
        $this->setData(self::LEVEL, $level);
        return $this;
    }

    public function setUrlKey(string $urlKey): self
    {
        $this->setData(self::URL_KEY, $urlKey);
        return $this;
    }

    public function setName(string $name): self
    {
        $this->setData(self::NAME, $name);
        return $this;
    }

    public function setDescription(?string $description): self
    {
        $this->setData(self::DESCRIPTION, $description);
        return $this;
    }

    public function setImage(?string $image): self
    {
        $this->setData(self::IMAGE, $image);
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

    public function setMetaRobots(string $metaRobots): self
    {
        $this->setData(self::META_ROBOTS, $metaRobots);
        return $this;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->setData(self::SORT_ORDER, $sortOrder);
        return $this;
    }

    public function setIsActive(int $isActive): self
    {
        $this->setData(self::IS_ACTIVE, $isActive);
        return $this;
    }

    public function setPostCount(int $postCount): self
    {
        $this->setData(self::POST_COUNT, $postCount);
        return $this;
    }

    public function setPostsPerPage(?int $postsPerPage): self
    {
        $this->setData(self::POSTS_PER_PAGE, $postsPerPage);
        return $this;
    }

    public function setTemplate(string $template): self
    {
        $this->setData(self::TEMPLATE, $template);
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
}
