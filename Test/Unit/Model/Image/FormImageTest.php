<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model\Image;

use Magento\Catalog\Model\ImageUploader;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Model\Image\FormImage;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class FormImageTest extends TestCase
{
    private function formImage(
        ?ImageUploader $uploader = null,
        ?ReadInterface $media = null,
        ?LoggerInterface $logger = null,
        bool $storeFails = false
    ): FormImage {
        $storeManager = $this->createStub(StoreManagerInterface::class);
        if ($storeFails) {
            $storeManager->method('getStore')->willThrowException(new \RuntimeException('no store'));
        } else {
            $store = $this->createStub(Store::class);
            $store->method('getBaseUrl')->willReturn('https://shop.test/media/');
            $storeManager->method('getStore')->willReturn($store);
        }

        $filesystem = $this->createStub(Filesystem::class);
        if ($media === null) {
            $media = $this->createStub(ReadInterface::class);
            $media->method('isFile')->willReturn(false);
        }
        $filesystem->method('getDirectoryRead')->willReturn($media);

        return new FormImage(
            $uploader ?? $this->createStub(ImageUploader::class),
            $storeManager,
            $filesystem,
            $logger ?? $this->createStub(LoggerInterface::class)
        );
    }

    public function testToFormValueForEmptyAndArrayValues(): void
    {
        $image = $this->formImage();
        $this->assertSame([], $image->toFormValue(''));
        $this->assertSame([], $image->toFormValue(null));
        $this->assertSame([['file' => 'x']], $image->toFormValue([['file' => 'x']]));
    }

    public function testToFormValueForAbsoluteUrl(): void
    {
        $this->assertSame(
            [['name' => 'pic.jpg', 'file' => 'https://cdn.test/a/pic.jpg?v=1', 'url' => 'https://cdn.test/a/pic.jpg?v=1', 'type' => 'image']],
            $this->formImage()->toFormValue('https://cdn.test/a/pic.jpg?v=1')
        );
    }

    public function testToFormValueForRelativePathIncludesSizeWhenFileExists(): void
    {
        $media = $this->createMock(ReadInterface::class);
        $media->expects($this->once())->method('isFile')->with('blog/2026/hero.png')->willReturn(true);
        $media->expects($this->once())->method('stat')->with('blog/2026/hero.png')->willReturn(['size' => '2048']);

        $this->assertSame(
            [['name' => 'hero.png', 'file' => '/2026/hero.png', 'url' => 'https://shop.test/media/blog/2026/hero.png', 'type' => 'image', 'size' => 2048]],
            $this->formImage(null, $media)->toFormValue('/2026/hero.png')
        );
    }

    public function testToFormValueLogsStatFailuresAndFallsBackToDefaultMediaUrl(): void
    {
        $media = $this->createStub(ReadInterface::class);
        $media->method('isFile')->willThrowException(new \RuntimeException('io'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with($this->stringContains('io'));

        $result = $this->formImage(null, $media, $logger, true)->toFormValue('a.png');
        $this->assertSame('/media/blog/a.png', $result[0]['url']);
        $this->assertArrayNotHasKey('size', $result[0]);
    }

    public function testToFormDataOnlyTouchesPresentFields(): void
    {
        $data = $this->formImage()->toFormData(['image' => '', 'name' => 'n'], ['image', 'missing']);
        $this->assertSame(['image' => [], 'name' => 'n'], $data);
    }

    public function testFromFormValueScalars(): void
    {
        $image = $this->formImage();
        $this->assertNull($image->fromFormValue(null));
        $this->assertNull($image->fromFormValue('   '));
        $this->assertSame('x.png', $image->fromFormValue(' x.png '));
        $this->assertNull($image->fromFormValue(5));
        $this->assertNull($image->fromFormValue([]));
        $this->assertNull($image->fromFormValue(['flat']));
    }

    public function testFromFormValueExistingFile(): void
    {
        $image = $this->formImage();
        $this->assertSame('2026/a.png', $image->fromFormValue([['file' => '2026/a.png']]));
        $this->assertSame('b.png', $image->fromFormValue([['name' => 'b.png']]));
        $this->assertNull($image->fromFormValue([['file' => '../etc/passwd']]));
        $this->assertNull($image->fromFormValue([['file' => '']]));
    }

    public function testFromFormValueMovesUploadedTmpFile(): void
    {
        $uploader = $this->createMock(ImageUploader::class);
        $uploader->expects($this->exactly(2))->method('moveFileFromTmp')
            ->with('pic.png', true)
            ->willReturnOnConsecutiveCalls('blog/p/i/pic.png', '/other/pic.png');
        $image = $this->formImage($uploader);

        $this->assertSame('p/i/pic.png', $image->fromFormValue([['tmp_name' => '/tmp/x', 'file' => 'sub/pic.png']]));
        $this->assertSame('other/pic.png', $image->fromFormValue([['tmp_name' => '/tmp/x', 'name' => 'pic.png']]));
    }

    public function testFromFormValueRejectsUnsafeUploadNames(): void
    {
        $uploader = $this->createMock(ImageUploader::class);
        $uploader->expects($this->never())->method('moveFileFromTmp');
        $image = $this->formImage($uploader);

        $this->assertNull($image->fromFormValue([['tmp_name' => '/tmp/x', 'file' => '..']]));
        $this->assertNull($image->fromFormValue([['tmp_name' => '/tmp/x']]));
    }

    public function testApplyToPostDataConvertsEveryField(): void
    {
        $data = $this->formImage()->applyToPostData(['a' => [['file' => 'a.png']], 'keep' => 1], ['a', 'b']);
        $this->assertSame(['a' => 'a.png', 'keep' => 1, 'b' => null], $data);
    }
}
