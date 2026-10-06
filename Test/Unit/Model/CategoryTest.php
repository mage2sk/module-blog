<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Panth\Blog\Model\Category;
use Panth\Blog\Model\ResourceModel\Category as CategoryResource;
use Panth\Blog\Model\Tag;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\TestCase;

class CategoryTest extends TestCase
{
    use BlogTestHelpers;

    public function testDefaults(): void
    {
        $category = $this->makeModel(Category::class);
        $this->assertSame(1, $category->getLevel());
        $this->assertSame('grid', $category->getTemplate());
        $this->assertSame('index,follow', $category->getMetaRobots());
        $this->assertNull($category->getParentId());
        $this->assertNull($category->getPostsPerPage());
    }

    public function testChildIdsEmptyWithoutPathOrId(): void
    {
        $this->assertSame([], $this->makeModel(Category::class)->getChildCategoryIds());

        $withPath = $this->makeModel(Category::class, ['path' => '1/2']);
        $this->assertSame([], $withPath->getChildCategoryIds());
    }

    public function testChildIdsQueryDescendantsByPath(): void
    {
        $select = $this->selectStub();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->expects($this->once())->method('fetchCol')->with($select)->willReturn(['4', '9']);

        $resource = $this->createStub(CategoryResource::class);
        $resource->method('getIdFieldName')->willReturn('category_id');
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn('panth_blog_category');

        $category = $this->makeModel(Category::class, ['path' => '1/2'], $resource);
        $category->setId(2);

        $this->assertSame([4, 9], $category->getChildCategoryIds());
    }

    public function testTagModelDefaultsAndIdentities(): void
    {
        $tag = $this->makeModel(Tag::class, ['tag_id' => '8', 'post_count' => '3']);
        $tag->setId(8);
        $this->assertSame(8, $tag->getTagId());
        $this->assertSame(3, $tag->getPostCount());
        $this->assertSame('index,follow', $tag->getMetaRobots());
        $this->assertSame(['panth_blog_tag_8', 'panth_blog_tag'], $tag->getIdentities());
    }
}
