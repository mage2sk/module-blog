<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Plugin\Social;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Post;
use Panth\Blog\Plugin\Social\OgImageForBlogPostPlugin;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OgImageForBlogPostPluginTest extends TestCase
{
    use BlogTestHelpers;

    private function plugin(
        bool $enabled,
        array $route,
        string $slug,
        ?PostRepositoryInterface $repository = null,
        ?LoggerInterface $logger = null
    ): OgImageForBlogPostPlugin {
        $request = $this->createStub(Http::class);
        $request->method('getModuleName')->willReturn($route[0]);
        $request->method('getControllerName')->willReturn($route[1]);
        $request->method('getActionName')->willReturn($route[2]);
        $request->method('getParam')->willReturn($slug);

        $config = $this->createStub(Config::class);
        $config->method('isOgUseFeaturedImage')->willReturn($enabled);

        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturn('https://s.test/media/');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new OgImageForBlogPostPlugin(
            $request,
            $repository ?? $this->createStub(PostRepositoryInterface::class),
            $storeManager,
            $config,
            $logger ?? $this->createStub(LoggerInterface::class)
        );
    }

    private function repo(array $data): PostRepositoryInterface
    {
        $repo = $this->createStub(PostRepositoryInterface::class);
        $repo->method('getByUrlKey')->willReturn($this->makeModel(Post::class, $data));
        return $repo;
    }

    public function testOverridesOgImageOnPostView(): void
    {
        $tags = $this->plugin(true, ['blog', 'post', 'view'], 'hello', $this->repo([
            'title' => 'Hello',
            'featured_image' => '/2026/hero.jpg',
        ]))->afterResolve(null, ['og:title' => 'Hello', 'og:image' => 'old.png']);

        $this->assertSame('https://s.test/media/blog/2026/hero.jpg', $tags['og:image']);
        $this->assertSame($tags['og:image'], $tags['og:image:secure_url']);
        $this->assertSame('Hello', $tags['og:image:alt']);
        $this->assertSame('Hello', $tags['og:title']);
        $this->assertSame('image/png', $tags['og:image:type']);
    }

    public function testOgImageFieldWinsOverFeaturedImage(): void
    {
        $tags = $this->plugin(true, ['blog', 'post', 'view'], 'hello', $this->repo([
            'og_image' => 'og.png',
            'featured_image' => 'hero.jpg',
            'featured_image_alt' => 'Alt text',
        ]))->afterResolve(null, []);

        $this->assertSame('https://s.test/media/blog/og.png', $tags['og:image']);
        $this->assertSame('Alt text', $tags['og:image:alt']);
    }

    public function testUntouchedOutsidePostViewOrWhenDisabled(): void
    {
        $repo = $this->createMock(PostRepositoryInterface::class);
        $repo->expects($this->never())->method('getByUrlKey');
        $input = ['og:image' => 'x'];

        $this->assertSame($input, $this->plugin(true, ['blog', 'category', 'view'], 'hello', $repo)->afterResolve(null, $input));
        $this->assertSame($input, $this->plugin(false, ['blog', 'post', 'view'], 'hello', $repo)->afterResolve(null, $input));
        $this->assertSame($input, $this->plugin(true, ['blog', 'post', 'view'], '  ', $repo)->afterResolve(null, $input));
    }

    public function testPostWithoutImageKeepsTags(): void
    {
        $input = ['og:image' => 'x'];
        $this->assertSame($input, $this->plugin(true, ['blog', 'post', 'view'], 'a', $this->repo(['title' => 'T']))->afterResolve(null, $input));
    }

    public function testLookupFailureIsLogged(): void
    {
        $repo = $this->createStub(PostRepositoryInterface::class);
        $repo->method('getByUrlKey')->willThrowException(new NoSuchEntityException(__('gone')));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($this->stringContains('gone'));

        $input = ['og:image' => 'x'];
        $this->assertSame($input, $this->plugin(true, ['blog', 'post', 'view'], 'a', $repo, $logger)->afterResolve(null, $input));
    }
}
