<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model\Config\Source;

use Magento\Framework\DataObject;
use Panth\Blog\Api\Data\CommentInterface;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Model\Config\Source\Authors;
use Panth\Blog\Model\Config\Source\Categories;
use Panth\Blog\Model\Config\Source\CategoryTemplate;
use Panth\Blog\Model\Config\Source\CitationStyle;
use Panth\Blog\Model\Config\Source\CommentAllowFor;
use Panth\Blog\Model\Config\Source\CommentCaptchaProvider;
use Panth\Blog\Model\Config\Source\CommentStatus;
use Panth\Blog\Model\Config\Source\PostLayout;
use Panth\Blog\Model\Config\Source\PostStatus;
use Panth\Blog\Model\Config\Source\RelatedPostsStrategy;
use Panth\Blog\Model\Config\Source\WysiwygEditor;
use Panth\Blog\Model\ResourceModel\Author\Collection as AuthorCollection;
use Panth\Blog\Model\ResourceModel\Author\CollectionFactory as AuthorCollectionFactory;
use Panth\Blog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Panth\Blog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SourceModelsTest extends TestCase
{
    public static function staticSources(): array
    {
        return [
            [CategoryTemplate::class, ['grid', 'list', 'magazine']],
            [CitationStyle::class, ['numbered_footnote', 'inline_link', 'sidebar']],
            [CommentAllowFor::class, ['everyone', 'registered_only', 'nobody']],
            [CommentCaptchaProvider::class, ['math', 'cloudflare_turnstile', 'hcaptcha', 'recaptcha_v3', 'none']],
            [CommentStatus::class, [
                CommentInterface::STATUS_PENDING,
                CommentInterface::STATUS_APPROVED,
                CommentInterface::STATUS_SPAM,
                CommentInterface::STATUS_TRASH,
            ]],
            [PostLayout::class, ['default', 'no-sidebar', 'wide', 'longform']],
            [PostStatus::class, [
                PostInterface::STATUS_DRAFT,
                PostInterface::STATUS_SCHEDULED,
                PostInterface::STATUS_PUBLISHED,
                PostInterface::STATUS_ARCHIVED,
            ]],
            [RelatedPostsStrategy::class, ['manual_only', 'auto_only', 'manual_then_auto']],
            [WysiwygEditor::class, ['tinymce', 'hyva_cms', 'markdown']],
        ];
    }

    #[DataProvider('staticSources')]
    public function testStaticSourceValues(string $class, array $values): void
    {
        $options = (new $class())->toOptionArray();
        $this->assertSame($values, array_column($options, 'value'));
        foreach ($options as $option) {
            $this->assertNotSame('', (string) $option['label']);
        }
    }

    public function testAuthorsListsActiveAuthors(): void
    {
        $collection = $this->createMock(AuthorCollection::class);
        $collection->expects($this->once())->method('addFieldToFilter')->with('is_active', 1)->willReturnSelf();
        $collection->expects($this->once())->method('setOrder')->with('display_name', 'ASC')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([
            new class (['author_id' => '2', 'display_name' => 'Ann']) extends DataObject {
                public function getAuthorId()
                {
                    return $this->getData('author_id');
                }
            },
        ]));
        $factory = $this->createStub(AuthorCollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $this->assertSame([['value' => 2, 'label' => 'Ann']], (new Authors($factory))->toOptionArray());
    }

    public function testAuthorsReturnsEmptyOnFailure(): void
    {
        $factory = $this->createStub(AuthorCollectionFactory::class);
        $factory->method('create')->willThrowException(new \RuntimeException('db'));
        $this->assertSame([], (new Authors($factory))->toOptionArray());
    }

    public function testCategoriesAreIndentedByLevel(): void
    {
        $collection = $this->createMock(CategoryCollection::class);
        $collection->expects($this->once())->method('addFieldToFilter')->with('is_active', 1)->willReturnSelf();
        $collection->expects($this->exactly(2))->method('setOrder')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([
            new DataObject(['category_id' => '1', 'name' => 'Root', 'level' => 0]),
            new DataObject(['category_id' => '5', 'name' => 'Child', 'level' => 2]),
        ]));
        $factory = $this->createStub(CategoryCollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $this->assertSame([
            ['value' => 1, 'label' => 'Root'],
            ['value' => 5, 'label' => '- - Child'],
        ], (new Categories($factory))->toOptionArray());
    }

    public function testCategoriesReturnsEmptyOnFailure(): void
    {
        $factory = $this->createStub(CategoryCollectionFactory::class);
        $factory->method('create')->willThrowException(new \RuntimeException('db'));
        $this->assertSame([], (new Categories($factory))->toOptionArray());
    }

    public function testTagsAndPostsSources(): void
    {
        $tags = $this->createMock(\Panth\Blog\Model\ResourceModel\Tag\Collection::class);
        $tags->expects($this->once())->method('setOrder')->with('name', 'ASC')->willReturnSelf();
        $tags->method('getIterator')->willReturn(new \ArrayIterator([
            new DataObject(['tag_id' => '5', 'name' => 'Tips']),
        ]));
        $tagFactory = $this->createStub(\Panth\Blog\Model\ResourceModel\Tag\CollectionFactory::class);
        $tagFactory->method('create')->willReturn($tags);
        $this->assertSame([['value' => 5, 'label' => 'Tips']], (new \Panth\Blog\Model\Config\Source\Tags($tagFactory))->toOptionArray());

        $posts = $this->createStub(\Panth\Blog\Model\ResourceModel\Post\Collection::class);
        $posts->method('addFieldToSelect')->willReturnSelf();
        $posts->method('setOrder')->willReturnSelf();
        $posts->method('getIterator')->willReturn(new \ArrayIterator([
            new DataObject(['post_id' => '13', 'title' => 'Guide', 'status' => 'published']),
        ]));
        $postFactory = $this->createStub(\Panth\Blog\Model\ResourceModel\Post\CollectionFactory::class);
        $postFactory->method('create')->willReturn($posts);
        $this->assertSame(
            [['value' => 13, 'label' => 'Guide (#13, published)']],
            (new \Panth\Blog\Model\Config\Source\Posts($postFactory))->toOptionArray()
        );

        $broken = $this->createStub(\Panth\Blog\Model\ResourceModel\Tag\CollectionFactory::class);
        $broken->method('create')->willThrowException(new \RuntimeException('db'));
        $this->assertSame([], (new \Panth\Blog\Model\Config\Source\Tags($broken))->toOptionArray());
    }
}
