<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Console\Command;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Api\AuthorRepositoryInterface;
use Panth\Blog\Api\CategoryRepositoryInterface;
use Panth\Blog\Api\Data\AuthorInterfaceFactory;
use Panth\Blog\Api\Data\CategoryInterfaceFactory;
use Panth\Blog\Api\Data\PostInterfaceFactory;
use Panth\Blog\Api\Data\TagInterfaceFactory;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Api\TagRepositoryInterface;
use Panth\Blog\Console\Command\SeedDemoCommand;
use Panth\Blog\Console\Command\UrlRewriteRebuildCommand;
use Panth\Blog\Model\Author;
use Panth\Blog\Model\Category;
use Panth\Blog\Model\Post;
use Panth\Blog\Model\ResourceModel\Post as PostResource;
use Panth\Blog\Model\Tag;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class DataCommandsTest extends TestCase
{
    use BlogTestHelpers;

    private function store(int $id, ?string $front): Store
    {
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn($id);
        $store->method('getConfig')->willReturn($front);
        return $store;
    }

    private function rewriteTester(AdapterInterface $connection, array $stores): CommandTester
    {
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn($stores);
        return new CommandTester(new UrlRewriteRebuildCommand($this->createStub(State::class), $resource, $storeManager));
    }

    public function testRewriteRebuildFailsWithoutRewriteTable(): void
    {
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('isTableExists')->willReturn(false);
        $tester = $this->rewriteTester($connection, []);
        $this->assertSame(1, $tester->execute([]));
        $this->assertStringContainsString('url_rewrite table missing', $tester->getDisplay());
    }

    public function testRewriteRebuildInsertsRewritesPerStore(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($this->selectStub());
        $connection->method('isTableExists')->willReturnCallback(
            static fn ($t) => in_array($t, ['url_rewrite', 'panth_blog_post', 'panth_blog_tag'], true)
        );
        $connection->method('fetchAll')->willReturnOnConsecutiveCalls(
            [['post_id' => 1, 'url_key' => 'hello'], ['post_id' => 2, 'url_key' => ''], ['post_id' => 0, 'url_key' => 'x']],
            [['tag_id' => 3, 'url_key' => 'php']]
        );
        $connection->expects($this->exactly(4))->method('delete');
        $inserts = [];
        $connection->method('insert')->willReturnCallback(function ($table, $row) use (&$inserts) {
            if ($row['request_path'] === 'news/tag/php') {
                throw new \RuntimeException('duplicate');
            }
            $inserts[] = $row['store_id'] . ':' . $row['request_path'] . '>' . $row['target_path'];
            return 1;
        });

        $tester = $this->rewriteTester($connection, [$this->store(1, '/news/'), $this->store(2, '')]);
        $this->assertSame(0, $tester->execute([]));

        $this->assertSame([
            '1:news/hello>blog/post/view/slug/hello',
            '2:blog/hello>blog/post/view/slug/hello',
            '2:blog/tag/php>blog/tag/view/slug/php',
        ], $inserts);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Skip panth_blog_tag:3 - duplicate', $display);
        $this->assertStringContainsString('Rebuilt 3 URL rewrites.', $display);
    }

    private function seedCommand(bool $exists, PostResource $postResource, array &$saved): SeedDemoCommand
    {
        $finder = function (string $class) use ($exists) {
            return function () use ($exists, $class) {
                if (!$exists) {
                    throw new NoSuchEntityException();
                }
                return $this->makeModel($class);
            };
        };

        $authorRepo = $this->createStub(AuthorRepositoryInterface::class);
        $authorRepo->method('getByUrlKey')->willReturnCallback($finder(Author::class));
        $categoryRepo = $this->createStub(CategoryRepositoryInterface::class);
        $categoryRepo->method('getByUrlKey')->willReturnCallback($finder(Category::class));
        $tagRepo = $this->createStub(TagRepositoryInterface::class);
        $tagRepo->method('getByUrlKey')->willReturnCallback($finder(Tag::class));
        $postRepo = $this->createStub(PostRepositoryInterface::class);
        $postRepo->method('getByUrlKey')->willReturnCallback($finder(Post::class));

        $counter = 0;
        $saver = function ($entity) use (&$saved, &$counter) {
            $counter++;
            $field = match (true) {
                $entity instanceof Author => 'author_id',
                $entity instanceof Category => 'category_id',
                $entity instanceof Tag => 'tag_id',
                default => 'post_id',
            };
            $entity->setData($field, $counter);
            $saved[] = $entity;
            return $entity;
        };
        $authorRepo->method('save')->willReturnCallback($saver);
        $categoryRepo->method('save')->willReturnCallback($saver);
        $tagRepo->method('save')->willReturnCallback($saver);
        $postRepo->method('save')->willReturnCallback($saver);

        $factory = function (string $factoryClass, string $modelClass) {
            $f = $this->createStub($factoryClass);
            $f->method('create')->willReturnCallback(fn () => $this->makeModel($modelClass));
            return $f;
        };

        return new SeedDemoCommand(
            $this->createStub(State::class),
            $authorRepo,
            $factory(AuthorInterfaceFactory::class, Author::class),
            $categoryRepo,
            $factory(CategoryInterfaceFactory::class, Category::class),
            $tagRepo,
            $factory(TagInterfaceFactory::class, Tag::class),
            $postRepo,
            $factory(PostInterfaceFactory::class, Post::class),
            $postResource
        );
    }

    public function testSeedDemoCreatesEverythingOnEmptyStore(): void
    {
        $postResource = $this->createMock(PostResource::class);
        $postResource->expects($this->exactly(5))->method('saveCategoryLinks');
        $postResource->expects($this->exactly(5))->method('saveTagLinks');
        $postResource->expects($this->exactly(5))->method('saveStoreLinks')->with($this->anything(), [0]);

        $saved = [];
        $tester = new CommandTester($this->seedCommand(false, $postResource, $saved));
        $this->assertSame(0, $tester->execute([]));

        $this->assertCount(20, $saved);
        $posts = array_values(array_filter($saved, static fn ($e) => $e instanceof Post));
        $this->assertCount(5, $posts);
        $this->assertSame('published', $posts[0]->getStatus());
        $this->assertSame(1, $posts[0]->getAuthorId());
        $categories = array_values(array_filter($saved, static fn ($e) => $e instanceof Category));
        $this->assertSame(1, (int) $categories[0]->getData('is_active'));
        $this->assertSame('grid', $categories[0]->getTemplate());
        $this->assertStringContainsString('+ post: Getting started with Hyva', $tester->getDisplay());
        $this->assertStringContainsString('Done.', $tester->getDisplay());
    }

    public function testSeedDemoIsIdempotent(): void
    {
        $postResource = $this->createMock(PostResource::class);
        $postResource->expects($this->never())->method('saveCategoryLinks');

        $saved = [];
        $tester = new CommandTester($this->seedCommand(true, $postResource, $saved));
        $this->assertSame(0, $tester->execute([]));
        $this->assertSame([], $saved);
        $this->assertSame(20, substr_count($tester->getDisplay(), ' exists: '));
    }
}
