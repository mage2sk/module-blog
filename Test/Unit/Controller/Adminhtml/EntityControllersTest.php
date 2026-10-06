<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Controller\Adminhtml;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Ui\Component\MassAction\Filter;
use Panth\Blog\Model\Image\FormImage;
use Panth\Blog\Test\Unit\Support\AdminControllerHelpers;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EntityControllersTest extends TestCase
{
    private ?object $linkManager = null;

    use AdminControllerHelpers;
    use BlogTestHelpers;

    public static function entities(): array
    {
        return [
            'post' => ['Post', 'post'],
            'author' => ['Author', 'author'],
            'category' => ['Category', 'category'],
            'comment' => ['Comment', 'comment'],
            'tag' => ['Tag', 'tag'],
        ];
    }

    public static function entityNames(): array
    {
        return array_map(static fn (array $row) => [$row[0]], self::entities());
    }

    private function ns(string $entity, string $action): string
    {
        return 'Panth\\Blog\\Controller\\Adminhtml\\' . $entity . '\\' . $action;
    }

    private function repoClass(string $entity): string
    {
        return 'Panth\\Blog\\Api\\' . $entity . 'RepositoryInterface';
    }

    private function model(string $entity, array $data = [], ?int $id = null): object
    {
        $model = $this->makeModel('Panth\\Blog\\Model\\' . $entity, $data);
        if ($id !== null) {
            $model->setId($id);
            $model->setData(strtolower($entity) . '_id', $id);
        }
        return $model;
    }

    public static function aclProvider(): array
    {
        return [
            ['Post', 'Delete', 'Panth_Blog::post_delete'],
            ['Post', 'MassDelete', 'Panth_Blog::post_delete'],
            ['Post', 'Save', 'Panth_Blog::post_save'],
            ['Post', 'MassStatus', 'Panth_Blog::post_save'],
            ['Post', 'Duplicate', 'Panth_Blog::post_save'],
            ['Post', 'Index', 'Panth_Blog::post'],
            ['Author', 'Delete', 'Panth_Blog::author_delete'],
            ['Author', 'Save', 'Panth_Blog::author_save'],
            ['Author', 'Edit', 'Panth_Blog::author'],
            ['Category', 'MassDelete', 'Panth_Blog::category_delete'],
            ['Category', 'MassStatus', 'Panth_Blog::category_save'],
            ['Tag', 'Delete', 'Panth_Blog::tag_delete'],
            ['Tag', 'Save', 'Panth_Blog::tag_save'],
            ['Comment', 'Delete', 'Panth_Blog::comment'],
            ['Comment', 'Save', 'Panth_Blog::comment'],
            ['Comment', 'MassStatus', 'Panth_Blog::comment'],
        ];
    }

    #[DataProvider('aclProvider')]
    public function testAdminResources(string $entity, string $action, string $resource): void
    {
        $this->assertSame($resource, constant($this->ns($entity, $action) . '::ADMIN_RESOURCE'));
    }

    #[DataProvider('entities')]
    public function testDeleteRemovesEntityAndRedirects(string $entity, string $noun): void
    {
        $repo = $this->createMock($this->repoClass($entity));
        $repo->expects($this->once())->method('deleteById')->with(4);
        $this->params = ['id' => '4'];

        $class = $this->ns($entity, 'Delete');
        (new $class($this->adminContext(), $repo))->execute();

        $this->assertSame(['*/*/', []], $this->redirect);
        $this->assertSame(['The ' . $noun . ' has been deleted.'], $this->messagesOf('success'));
    }

    #[DataProvider('entityNames')]
    public function testDeleteWithoutIdOrFailing(string $entity): void
    {
        $repo = $this->createMock($this->repoClass($entity));
        $repo->expects($this->once())->method('deleteById')->willThrowException(new \RuntimeException('in use'));
        $class = $this->ns($entity, 'Delete');

        $this->params = [];
        (new $class($this->adminContext(), $repo))->execute();
        $this->assertSame([], $this->messages);

        $this->params = ['id' => 2];
        (new $class($this->adminContext(), $repo))->execute();
        $this->assertSame(['in use'], $this->messagesOf('error'));
        $this->assertSame(['*/*/', []], $this->redirect);
    }

    #[DataProvider('entities')]
    public function testEditExistingEntitySetsTitle(string $entity, string $noun): void
    {
        $repo = $this->createMock($this->repoClass($entity));
        $repo->expects($this->once())->method('getById')->with(3)->willReturn($this->model($entity, [], 3));
        $factory = $this->createMock('Panth\\Blog\\Api\\Data\\' . $entity . 'InterfaceFactory');
        $factory->expects($this->never())->method('create');
        $this->params = ['id' => '3'];

        $class = $this->ns($entity, 'Edit');
        (new $class($this->adminContext(), $this->pageFactory(), $repo, $factory))->execute();

        $this->assertSame('Panth_Blog::' . $noun, $this->activeMenu);
        $this->assertSame(['Edit ' . ucfirst($noun)], $this->titles);
    }

    #[DataProvider('entities')]
    public function testEditNewEntityAndMissingEntity(string $entity, string $noun): void
    {
        $factory = $this->createMock('Panth\\Blog\\Api\\Data\\' . $entity . 'InterfaceFactory');
        $factory->expects($this->once())->method('create');
        $repo = $this->createStub($this->repoClass($entity));
        $repo->method('getById')->willThrowException(new NoSuchEntityException());
        $class = $this->ns($entity, 'Edit');

        (new $class($this->adminContext(), $this->pageFactory(), $repo, $factory))->execute();
        $this->assertSame(['New ' . ucfirst($noun)], $this->titles);

        $this->params = ['id' => 9];
        (new $class($this->adminContext(), $this->pageFactory(), $repo, $factory))->execute();
        $this->assertSame(['This ' . $noun . ' no longer exists.'], $this->messagesOf('error'));
        $this->assertSame(['*/*/', []], $this->redirect);
    }

    #[DataProvider('entities')]
    public function testIndexPage(string $entity, string $noun): void
    {
        $class = $this->ns($entity, 'Index');
        (new $class($this->adminContext(), $this->pageFactory()))->execute();
        $this->assertSame('Panth_Blog::' . $noun, $this->activeMenu);
        $this->assertCount(1, $this->titles);
        $this->assertStringStartsWith('Blog ', $this->titles[0]);
    }

    #[DataProvider('entityNames')]
    public function testNewActionForwardsToEdit(string $entity): void
    {
        $forward = $this->createMock(Forward::class);
        $forward->expects($this->once())->method('forward')->with('edit')->willReturnSelf();
        $this->resultFactory = $this->createMock(ResultFactory::class);
        $this->resultFactory->expects($this->once())->method('create')->with(ResultFactory::TYPE_FORWARD)->willReturn($forward);

        $class = $this->ns($entity, 'NewAction');
        $this->assertSame($forward, (new $class($this->adminContext()))->execute());
    }

    private function massFilter(string $entity, array $items, array $ids = []): Filter
    {
        $collection = $this->createStub('Panth\\Blog\\Model\\ResourceModel\\' . $entity . '\\Collection');
        $collection->method('getIterator')->willReturn(new \ArrayIterator($items));
        $collection->method('getAllIds')->willReturn($ids);
        $filter = $this->createStub(Filter::class);
        $filter->method('getCollection')->willReturn($collection);
        return $filter;
    }

    private function collectionFactory(string $entity): object
    {
        $factory = $this->createStub('Panth\\Blog\\Model\\ResourceModel\\' . $entity . '\\CollectionFactory');
        $factory->method('create')->willReturn($this->createStub('Panth\\Blog\\Model\\ResourceModel\\' . $entity . '\\Collection'));
        return $factory;
    }

    #[DataProvider('entityNames')]
    public function testMassDeleteCountsSuccesses(string $entity): void
    {
        $a = $this->model($entity, [], 1);
        $b = $this->model($entity, [], 2);
        $repo = $this->createStub($this->repoClass($entity));
        $repo->method('delete')->willReturnCallback(static function ($item) use ($b) {
            if ($item === $b) {
                throw new \RuntimeException('locked');
            }
            return true;
        });

        $class = $this->ns($entity, 'MassDelete');
        (new $class($this->adminContext(), $this->massFilter($entity, [$a, $b]), $this->collectionFactory($entity), $repo))->execute();

        $this->assertSame(['locked'], $this->messagesOf('error'));
        $this->assertCount(1, $this->messagesOf('success'));
        $this->assertStringContainsString('A total of 1 ', $this->messagesOf('success')[0]);
        $this->assertSame(['*/*/', []], $this->redirect);
    }

    #[DataProvider('entityNames')]
    public function testMassDeleteWithNothingDeletedHasNoSuccess(string $entity): void
    {
        $class = $this->ns($entity, 'MassDelete');
        (new $class($this->adminContext(), $this->massFilter($entity, []), $this->collectionFactory($entity), $this->createStub($this->repoClass($entity))))->execute();
        $this->assertSame([], $this->messages);
    }

    public static function massStatusProvider(): array
    {
        return [
            'post' => ['Post', 'status', 'published', 'status', 'published'],
            'comment' => ['Comment', 'status', 'spam', 'status', 'spam'],
            'author enable' => ['Author', 'type', 'enable', 'is_active', 1],
            'category disable' => ['Category', 'type', 'disable', 'is_active', 0],
            'tag noindex' => ['Tag', 'type', 'noindex', 'meta_robots', 'noindex,follow'],
            'tag index' => ['Tag', 'type', 'index', 'meta_robots', 'index,follow'],
        ];
    }

    #[DataProvider('massStatusProvider')]
    public function testMassStatusUpdatesEachEntity(string $entity, string $param, string $value, string $field, mixed $expected): void
    {
        $first = $this->model($entity, [], 1);
        $repo = $this->createMock($this->repoClass($entity));
        $repo->method('getById')->willReturnCallback(static function (int $id) use ($first) {
            if ($id === 2) {
                throw new NoSuchEntityException(__('missing'));
            }
            return $first;
        });
        $repo->expects($this->once())->method('save')->with($first);
        $this->params = [$param => $value];

        $class = $this->ns($entity, 'MassStatus');
        (new $class($this->adminContext(), $this->massFilter($entity, [], ['1', '2']), $this->collectionFactory($entity), $repo))->execute();

        $this->assertSame($expected, $first->getData($field));
        $this->assertCount(1, $this->messagesOf('success'));
        $this->assertCount(1, $this->messagesOf('error'));
        $this->assertSame(['*/*/', []], $this->redirect);
    }

    public static function invalidMassStatusProvider(): array
    {
        return [
            ['Post', 'status', 'deleted'],
            ['Comment', 'status', 'published'],
            ['Author', 'type', 'published'],
            ['Category', 'type', ''],
            ['Tag', 'type', 'nofollow'],
        ];
    }

    #[DataProvider('invalidMassStatusProvider')]
    public function testMassStatusRejectsUnknownValues(string $entity, string $param, string $value): void
    {
        $repo = $this->createMock($this->repoClass($entity));
        $repo->expects($this->never())->method('getById');
        $filter = $this->createMock(Filter::class);
        $filter->expects($this->never())->method('getCollection');
        $this->params = [$param => $value];

        $class = $this->ns($entity, 'MassStatus');
        (new $class($this->adminContext(), $filter, $this->collectionFactory($entity), $repo))->execute();

        $this->assertCount(1, $this->messagesOf('error'));
        $this->assertSame(['*/*/', []], $this->redirect);
    }

    private function saveController(string $entity, object $repo, object $factory, DataPersistorInterface $persistor): object
    {
        $class = $this->ns($entity, 'Save');
        $args = [$this->adminContext(), $repo, $factory, $persistor];
        if (in_array($entity, ['Post', 'Author', 'Category'], true)) {
            $formImage = $this->createStub(FormImage::class);
            $formImage->method('applyToPostData')->willReturnCallback(static function (array $data, array $fields) {
                foreach ($fields as $field) {
                    $data[$field] = 'img:' . $field;
                }
                return $data;
            });
            $args[] = $formImage;
        }
        if ($entity === 'Post') {
            $args[] = $this->linkManager ?? $this->createStub(\Panth\Blog\Model\Post\LinkManager::class);
        }
        if ($entity === 'Category') {
            $args[] = $this->createStub(\Panth\Blog\Model\Category\StoreLinkManager::class);
        }
        return new $class(...$args);
    }

    #[DataProvider('entityNames')]
    public function testSaveWithoutPostDataRedirectsToGrid(string $entity): void
    {
        $repo = $this->createMock($this->repoClass($entity));
        $repo->expects($this->never())->method('save');
        $this->saveController($entity, $repo, $this->createStub('Panth\\Blog\\Api\\Data\\' . $entity . 'InterfaceFactory'), $this->createStub(DataPersistorInterface::class))->execute();
        $this->assertSame(['*/*/', []], $this->redirect);
    }

    #[DataProvider('entities')]
    public function testSaveCreatesNewEntityAndContinuesEditing(string $entity, string $noun): void
    {
        $idField = $noun . '_id';
        $model = $this->model($entity);
        $factory = $this->createStub('Panth\\Blog\\Api\\Data\\' . $entity . 'InterfaceFactory');
        $factory->method('create')->willReturn($model);
        $repo = $this->createMock($this->repoClass($entity));
        $repo->expects($this->once())->method('save')->with($model)->willReturnCallback(static function ($m) use ($idField) {
            $m->setData($idField, 31);
            return $m;
        });
        $persistor = $this->createMock(DataPersistorInterface::class);
        $persistor->expects($this->once())->method('clear')->with('panth_blog_' . $noun);
        $persistor->expects($this->never())->method('set');

        $this->postValue = [$idField => '', 'name' => 'New thing'];
        $this->params = ['back' => 'edit'];
        $this->saveController($entity, $repo, $factory, $persistor)->execute();

        $this->assertSame('New thing', $model->getData('name'));
        $this->assertArrayNotHasKey($idField, array_filter($model->getData(), static fn ($v) => $v === ''));
        $this->assertSame(['*/*/edit', ['id' => 31]], $this->redirect);
        $this->assertSame(['The ' . $noun . ' has been saved.'], $this->messagesOf('success'));
    }

    #[DataProvider('entities')]
    public function testSaveExistingEntityMergesData(string $entity, string $noun): void
    {
        $model = $this->model($entity, ['name' => 'Old', 'keep' => 'yes'], 8);
        $repo = $this->createStub($this->repoClass($entity));
        $repo->method('getById')->willReturn($model);
        $repo->method('save')->willReturnArgument(0);

        $this->postValue = ['name' => 'Renamed'];
        $this->params = ['id' => '8'];
        $this->saveController($entity, $repo, $this->createStub('Panth\\Blog\\Api\\Data\\' . $entity . 'InterfaceFactory'), $this->createStub(DataPersistorInterface::class))->execute();

        $this->assertSame('Renamed', $model->getData('name'));
        $this->assertSame('yes', $model->getData('keep'));
        $this->assertSame(['*/*/', []], $this->redirect);
    }

    public function testSaveAppliesImageFieldsPerEntity(): void
    {
        $expectations = ['Post' => ['featured_image', 'og_image'], 'Author' => ['avatar'], 'Category' => ['image']];
        foreach ($expectations as $entity => $fields) {
            $model = $this->model($entity);
            $factory = $this->createStub('Panth\\Blog\\Api\\Data\\' . $entity . 'InterfaceFactory');
            $factory->method('create')->willReturn($model);
            $repo = $this->createStub($this->repoClass($entity));
            $repo->method('save')->willReturnArgument(0);
            $this->postValue = ['name' => 'x'];
            $this->saveController($entity, $repo, $factory, $this->createStub(DataPersistorInterface::class))->execute();
            foreach ($fields as $field) {
                $this->assertSame('img:' . $field, $model->getData($field), $entity . ' ' . $field);
            }
        }
    }

    public function testCommentSaveKeepsVisitorFieldsReadOnly(): void
    {
        $model = $this->model('Comment', ['author_name' => 'Visitor', 'author_email' => 'v@x.y', 'post_id' => 3], 5);
        $repo = $this->createStub($this->repoClass('Comment'));
        $repo->method('getById')->willReturn($model);
        $repo->method('save')->willReturnArgument(0);

        $this->postValue = ['author_name' => 'Hacker', 'author_email' => 'h@x.y', 'post_id' => 99, 'status' => 'approved', 'content' => 'Edited'];
        $this->params = ['id' => 5];
        $this->saveController('Comment', $repo, $this->createStub('Panth\\Blog\\Api\\Data\\CommentInterfaceFactory'), $this->createStub(DataPersistorInterface::class))->execute();

        $this->assertSame('Visitor', $model->getData('author_name'));
        $this->assertSame('v@x.y', $model->getData('author_email'));
        $this->assertSame(3, $model->getData('post_id'));
        $this->assertSame('approved', $model->getData('status'));
        $this->assertSame('Edited', $model->getData('content'));
    }

    public static function saveFailures(): array
    {
        $rows = [];
        foreach (self::entities() as $key => [$entity, $noun]) {
            $rows[$key . ' missing'] = [$entity, $noun, new NoSuchEntityException(), 'error', 'This ' . $noun . ' no longer exists.'];
            $rows[$key . ' localized'] = [$entity, $noun, new LocalizedException(__('URL key taken')), 'error', 'URL key taken'];
            $rows[$key . ' generic'] = [$entity, $noun, new \RuntimeException('db'), 'exception', 'Something went wrong while saving the ' . $noun . '.'];
        }
        return $rows;
    }

    #[DataProvider('saveFailures')]
    public function testSaveFailurePersistsFormData(string $entity, string $noun, \Throwable $error, string $type, string $message): void
    {
        $repo = $this->createStub($this->repoClass($entity));
        $repo->method('getById')->willReturn($this->model($entity, [], 6));
        $repo->method('save')->willThrowException($error);
        $persistor = $this->createMock(DataPersistorInterface::class);
        $persistor->expects($this->once())->method('set')->with('panth_blog_' . $noun, $this->arrayHasKey('name'));

        $this->postValue = ['name' => 'Draft'];
        $this->params = ['id' => 6];
        $this->saveController($entity, $repo, $this->createStub('Panth\\Blog\\Api\\Data\\' . $entity . 'InterfaceFactory'), $persistor)->execute();

        $this->assertSame([$message], $this->messagesOf($type));
        $this->assertSame(['*/*/edit', ['id' => 6]], $this->redirect);
    }
}
