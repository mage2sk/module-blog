<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Controller\Adminhtml;

use Magento\Catalog\Model\ImageUploader;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Panth\Blog\Api\Data\PostInterfaceFactory;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Controller\Adminhtml\Image\Upload;
use Panth\Blog\Controller\Adminhtml\Post\Duplicate;
use Panth\Blog\Model\Post;
use Panth\Blog\Test\Unit\Support\AdminControllerHelpers;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\TestCase;

class DuplicateAndUploadTest extends TestCase
{
    use AdminControllerHelpers;
    use BlogTestHelpers;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private string $dir = '';
    private array $json = [];

    protected function tearDown(): void
    {
        if ($this->dir !== '' && is_dir($this->dir)) {
            foreach (glob($this->dir . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->dir);
        }
    }

    private function tempFile(string $name, string $content): string
    {
        if ($this->dir === '') {
            $this->dir = sys_get_temp_dir() . '/panth_blog_upload_' . uniqid('', true);
            mkdir($this->dir, 0777, true);
        }
        $path = $this->dir . '/' . $name;
        file_put_contents($path, $content);
        return $path;
    }

    public function testDuplicateRequiresId(): void
    {
        $repo = $this->createMock(PostRepositoryInterface::class);
        $repo->expects($this->never())->method('getById');
        (new Duplicate($this->adminContext(), $repo, $this->createStub(PostInterfaceFactory::class)))->execute();
        $this->assertSame(['Missing post ID.'], $this->messagesOf('error'));
        $this->assertSame(['*/*/', []], $this->redirect);
    }

    public function testDuplicateCreatesDraftCopy(): void
    {
        $source = $this->makeModel(Post::class, [
            'post_id' => 4,
            'title' => 'Original',
            'url_key' => 'original',
            'status' => 'published',
            'published_at' => '2026-01-01 00:00:00',
            'view_count' => 50,
            'like_count' => 9,
            'created_at' => '2025-01-01',
            'updated_at' => '2025-02-01',
        ]);
        $clone = $this->makeModel(Post::class);
        $factory = $this->createStub(PostInterfaceFactory::class);
        $factory->method('create')->willReturn($clone);
        $repo = $this->createMock(PostRepositoryInterface::class);
        $repo->method('getById')->with(4)->willReturn($source);
        $repo->expects($this->once())->method('save')->with($clone)->willReturnCallback(static function ($post) {
            $post->setPostId(40);
            return $post;
        });

        $this->params = ['id' => '4'];
        (new Duplicate($this->adminContext(), $repo, $factory))->execute();

        $this->assertSame('Original', $clone->getTitle());
        $this->assertSame('original-copy', $clone->getUrlKey());
        $this->assertSame('draft', $clone->getStatus());
        $this->assertNull($clone->getPublishedAt());
        $this->assertSame(0, $clone->getViewCount());
        $this->assertSame(0, $clone->getLikeCount());
        $this->assertNull($clone->getCreatedAt());
        $this->assertSame(['*/*/edit', ['id' => 40]], $this->redirect);
        $this->assertSame(['The post has been duplicated.'], $this->messagesOf('success'));
    }

    public function testDuplicateFailureRedirectsToGrid(): void
    {
        $repo = $this->createStub(PostRepositoryInterface::class);
        $repo->method('getById')->willThrowException(new \RuntimeException('gone'));
        $this->params = ['id' => 4];
        (new Duplicate($this->adminContext(), $repo, $this->createStub(PostInterfaceFactory::class)))->execute();
        $this->assertSame(['gone'], $this->messagesOf('error'));
        $this->assertSame(['*/*/', []], $this->redirect);
    }

    private function upload(?ImageUploader $uploader = null): Upload
    {
        $json = $this->createStub(Json::class);
        $json->method('setData')->willReturnCallback(function ($data) use ($json) {
            $this->json = $data;
            return $json;
        });
        $this->resultFactory = $this->createStub(ResultFactory::class);
        $this->resultFactory->method('create')->willReturn($json);
        return new Upload($this->adminContext(), $uploader ?? $this->createStub(ImageUploader::class));
    }

    public function testUploadRejectsUnknownField(): void
    {
        $uploader = $this->createMock(ImageUploader::class);
        $uploader->expects($this->never())->method('saveFileToTmpDir');
        $this->params = ['param_name' => 'evil'];
        $this->upload($uploader)->execute();
        $this->assertSame('Unknown image field.', $this->json['error']);
    }

    public function testUploadRequiresFile(): void
    {
        $this->params = ['param_name' => 'avatar'];
        $this->upload()->execute();
        $this->assertSame('No image was uploaded.', $this->json['error']);
    }

    public function testUploadAcceptsRealPng(): void
    {
        $tmp = $this->tempFile('upload.tmp', base64_decode(self::PNG));
        $uploader = $this->createMock(ImageUploader::class);
        $uploader->expects($this->once())->method('saveFileToTmpDir')->with('featured_image')->willReturn(['file' => 'a.png']);
        $this->params = ['param_name' => 'featured_image', '__files' => ['featured_image' => ['name' => 'a.PNG', 'tmp_name' => $tmp]]];

        $this->upload($uploader)->execute();
        $this->assertSame(['file' => 'a.png'], $this->json);
    }

    public function testUploadRejectsMismatchedExtensionAndFakeImages(): void
    {
        $png = $this->tempFile('real.tmp', base64_decode(self::PNG));
        $fake = $this->tempFile('fake.tmp', '<?php echo 1;');
        $uploader = $this->createMock(ImageUploader::class);
        $uploader->expects($this->never())->method('saveFileToTmpDir');

        foreach ([
            ['name' => 'a.jpg', 'tmp_name' => $png],
            ['name' => 'a.png', 'tmp_name' => $fake],
            ['name' => 'a.php', 'tmp_name' => $png],
            ['name' => 'a.png', 'tmp_name' => $this->dir . '/missing'],
        ] as $file) {
            $this->params = ['param_name' => 'image', '__files' => ['image' => $file]];
            $this->upload($uploader)->execute();
            $this->assertSame('Only JPG, PNG, GIF and WebP images can be uploaded.', $this->json['error']);
        }
    }

    public function testUploadAclAcceptsAnyEntitySavePermission(): void
    {
        $method = new \ReflectionMethod(Upload::class, '_isAllowed');

        $this->authorization = $this->createStub(AuthorizationInterface::class);
        $this->authorization->method('isAllowed')->willReturnCallback(static fn ($r) => $r === 'Panth_Blog::author_save');
        $this->assertTrue($method->invoke($this->upload()));

        $this->authorization = $this->createStub(AuthorizationInterface::class);
        $this->authorization->method('isAllowed')->willReturn(false);
        $this->assertFalse($method->invoke($this->upload()));
    }
}
