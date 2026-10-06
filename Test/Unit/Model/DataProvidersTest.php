<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\DataObject;
use Panth\Blog\Model\Author\DataProvider as AuthorDataProvider;
use Panth\Blog\Model\Category\DataProvider as CategoryDataProvider;
use Panth\Blog\Model\Comment\DataProvider as CommentDataProvider;
use Panth\Blog\Model\Image\FormImage;
use Panth\Blog\Model\Post\DataProvider as PostDataProvider;
use Panth\Blog\Model\Tag\DataProvider as TagDataProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DataProvidersTest extends TestCase
{
    public static function providers(): array
    {
        return [
            'post' => [PostDataProvider::class, 'Post', 'post_id', 'panth_blog_post', ['featured_image', 'og_image']],
            'author' => [AuthorDataProvider::class, 'Author', 'author_id', 'panth_blog_author', ['avatar']],
            'category' => [CategoryDataProvider::class, 'Category', 'category_id', 'panth_blog_category', ['image']],
            'comment' => [CommentDataProvider::class, 'Comment', 'comment_id', 'panth_blog_comment', null],
            'tag' => [TagDataProvider::class, 'Tag', 'tag_id', 'panth_blog_tag', null],
        ];
    }

    private function build(string $class, string $entity, array $items, DataPersistorInterface $persistor, ?array $imageFields): object
    {
        $collection = $this->createStub('Panth\\Blog\\Model\\ResourceModel\\' . $entity . '\\Collection');
        $collection->method('getItems')->willReturn($items);
        $factory = $this->createStub('Panth\\Blog\\Model\\ResourceModel\\' . $entity . '\\CollectionFactory');
        $factory->method('create')->willReturn($collection);

        $args = ['form', 'id', 'id', $factory, $persistor];
        if ($imageFields !== null) {
            $formImage = $this->createStub(FormImage::class);
            $formImage->method('toFormData')->willReturnCallback(function (array $data, array $fields) use ($imageFields) {
                $this->assertSame($imageFields, $fields);
                $data['_images'] = true;
                return $data;
            });
            $args[] = $formImage;
        }
        if ($entity === 'Post') {
            $links = $this->createStub(\Panth\Blog\Model\Post\LinkManager::class);
            $links->method('getFormData')->willReturn(['store_ids' => ['0'], 'links_submitted' => '1']);
            $args[] = $links;
        }
        if ($entity === 'Category') {
            $links = $this->createStub(\Panth\Blog\Model\Category\StoreLinkManager::class);
            $links->method('getFormData')->willReturn(['store_ids' => ['2'], 'links_submitted' => '1']);
            $args[] = $links;
        }
        return new $class(...$args);
    }

    #[DataProvider('providers')]
    public function testLoadsItemsKeyedById(string $class, string $entity, string $idField, string $key, ?array $imageFields): void
    {
        $persistor = $this->createMock(DataPersistorInterface::class);
        $persistor->expects($this->once())->method('get')->with($key)->willReturn(null);
        $persistor->expects($this->never())->method('clear');

        $item = new DataObject([$idField => 5, 'name' => 'x', 'id' => 5]);
        $provider = $this->build($class, $entity, [$item], $persistor, $imageFields);

        $data = $provider->getData();
        $this->assertArrayHasKey(5, $data);
        $this->assertSame('x', $data[5]['name']);
        $this->assertSame($imageFields !== null, isset($data[5]['_images']));
        $this->assertSame($data, $provider->getData());
    }

    #[DataProvider('providers')]
    public function testPersistedFormDataOverridesLoadedValues(string $class, string $entity, string $idField, string $key, ?array $imageFields): void
    {
        $persistor = $this->createMock(DataPersistorInterface::class);
        $persistor->method('get')->with($key)->willReturn(['name' => 'edited']);
        $persistor->expects($this->once())->method('clear')->with($key);

        $item = new DataObject([$idField => 5, 'name' => 'original', 'id' => 5]);
        $data = $this->build($class, $entity, [$item], $persistor, $imageFields)->getData();

        $this->assertSame('edited', $data[5]['name']);
        $this->assertSame(5, $data[5][$idField]);
    }

    #[DataProvider('providers')]
    public function testPersistedDataForNewEntityUsesEmptyKey(string $class, string $entity, string $idField, string $key, ?array $imageFields): void
    {
        $persistor = $this->createStub(DataPersistorInterface::class);
        $persistor->method('get')->willReturn(['name' => 'draft']);

        $data = $this->build($class, $entity, [], $persistor, $imageFields)->getData();
        $this->assertSame(['' => [0 => false, 'name' => 'draft']], $data);
    }
}
