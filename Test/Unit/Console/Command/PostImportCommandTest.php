<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Console\Command;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Panth\Blog\Api\Data\PostInterfaceFactory;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Console\Command\PostImportCommand;
use Panth\Blog\Model\Post;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class PostImportCommandTest extends TestCase
{
    use BlogTestHelpers;

    private string $dir;
    private ?Post $saved = null;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/panth_blog_import_' . uniqid('', true);
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    private function file(string $content): string
    {
        $path = $this->dir . '/post.md';
        file_put_contents($path, $content);
        return $path;
    }

    private function tester(AdapterInterface $connection, ?PostRepositoryInterface $repository = null): CommandTester
    {
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $factory = $this->createStub(PostInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(fn () => $this->makeModel(Post::class));

        if ($repository === null) {
            $repository = $this->createStub(PostRepositoryInterface::class);
            $repository->method('save')->willReturnCallback(function (Post $post) {
                $post->setPostId(77);
                $this->saved = $post;
                return $post;
            });
        }

        return new CommandTester(new PostImportCommand(
            $this->createStub(State::class),
            $repository,
            $factory,
            $resource
        ));
    }

    public function testRejectsUnreadableFile(): void
    {
        $tester = $this->tester($this->connectionStub());
        $this->assertSame(1, $tester->execute(['file' => $this->dir . '/missing.md']));
        $this->assertStringContainsString('File not readable', $tester->getDisplay());
    }

    public function testRejectsEmptyFile(): void
    {
        $tester = $this->tester($this->connectionStub());
        $this->assertSame(1, $tester->execute(['file' => $this->file('')]));
        $this->assertStringContainsString('File is empty.', $tester->getDisplay());
    }

    public function testRequiresTitle(): void
    {
        $tester = $this->tester($this->connectionStub());
        $this->assertSame(1, $tester->execute(['file' => $this->file("---\nurl_key: x\n---\nBody")]));
        $this->assertStringContainsString('must include a "title"', $tester->getDisplay());
    }

    public function testImportsPostWithAuthorTagsAndCategories(): void
    {
        $connection = $this->createMock(Mysql::class);
        $connection->method('select')->willReturn($this->selectStub());
        $connection->method('isTableExists')->willReturn(true);
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls('5', '', '12', '30', '0');
        $connection->expects($this->once())->method('insert')
            ->with('panth_blog_tag', ['url_key' => 'new-tag', 'name' => 'New Tag']);
        $connection->method('lastInsertId')->willReturn('40');
        $links = [];
        $connection->method('insertOnDuplicate')->willReturnCallback(function ($table, $data) use (&$links) {
            $links[] = [$table, $data];
            return 1;
        });

        $md = "---\n"
            . "title: \"Hello \\\"World\\\"\"\n"
            . "# a comment\n"
            . "status: published\n"
            . "short_description: 'Short one'\n"
            . "published_at: 2026-05-01 10:00:00\n"
            . "author: jane\n"
            . "tags: New Tag, Existing\n"
            . "categories: News, Unknown\n"
            . "---\n<p>Body</p>";

        $tester = $this->tester($connection);
        $this->assertSame(0, $tester->execute(['file' => $this->file($md)]));
        $this->assertStringContainsString('Imported post #77 (hello-world).', $tester->getDisplay());

        $this->assertSame('Hello "World"', $this->saved->getTitle());
        $this->assertSame('published', $this->saved->getStatus());
        $this->assertSame('Short one', $this->saved->getShortDescription());
        $this->assertSame('2026-05-01 10:00:00', $this->saved->getPublishedAt());
        $this->assertSame(5, $this->saved->getAuthorId());
        $this->assertSame('<p>Body</p>', $this->saved->getContent());

        $this->assertSame([
            ['panth_blog_post_tag', ['post_id' => 77, 'tag_id' => 40]],
            ['panth_blog_post_tag', ['post_id' => 77, 'tag_id' => 12]],
            ['panth_blog_post_category', ['post_id' => 77, 'category_id' => 30, 'position' => 0, 'is_primary' => 1]],
        ], $links);
    }

    public function testDefaultsToDraftAndExplicitUrlKey(): void
    {
        $tester = $this->tester($this->connectionStub());
        $this->assertSame(0, $tester->execute(['file' => $this->file("---\ntitle: Plain\nurl_key: custom-key\n---\nText")]));
        $this->assertSame('draft', $this->saved->getStatus());
        $this->assertSame('custom-key', $this->saved->getUrlKey());
        $this->assertNull($this->saved->getShortDescription());
    }

    public function testCrlfFrontmatterIsNotRecognised(): void
    {
        $tester = $this->tester($this->connectionStub());
        $this->assertSame(1, $tester->execute(['file' => $this->file("---\r\ntitle: Windows\r\n---\r\nBody")]));
        $this->assertStringContainsString('must include a "title"', $tester->getDisplay());
    }

    public function testSaveFailureIsReported(): void
    {
        $repository = $this->createStub(PostRepositoryInterface::class);
        $repository->method('save')->willThrowException(new \RuntimeException('duplicate url key'));
        $tester = $this->tester($this->connectionStub(), $repository);
        $this->assertSame(1, $tester->execute(['file' => $this->file("---\ntitle: X\n---\nBody")]));
        $this->assertStringContainsString('duplicate url key', $tester->getDisplay());
    }
}
