<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Controller\Router;

use Magento\Framework\App\Action\Redirect;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\UrlInterface;
use Panth\Blog\Controller\Router\BlogRouter;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Url\UrlHistoryManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BlogRouterTest extends TestCase
{
    private array $created = [];
    private ?string $redirectedTo = null;

    protected function setUp(): void
    {
        $this->created = [];
        $this->redirectedTo = null;
    }

    private function router(array $existingSlugs = [], array $history = []): BlogRouter
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('limit')->willReturnSelf();

        $conn = $this->createMock(AdapterInterface::class);
        $conn->method('select')->willReturn($select);
        $conn->method('isTableExists')->willReturn(true);
        $conn->method('fetchOne')->willReturnCallback(
            static function () use ($existingSlugs) {
                return $existingSlugs ? array_shift($existingSlugs) : false;
            }
        );

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($conn);
        $resource->method('getTableName')->willReturnArgument(0);

        $config = $this->createMock(Config::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('getRouteFrontName')->willReturn('blog');

        $historyManager = $this->createMock(UrlHistoryManager::class);
        $historyManager->method('findCurrent')->willReturnCallback(
            static fn(string $type, string $slug) => $history[$type . ':' . $slug] ?? null
        );

        $actionFactory = $this->createMock(ActionFactory::class);
        $actionFactory->method('create')->willReturnCallback(
            function (string $class): ActionInterface {
                $this->created[] = $class;

                return $this->createMock(ActionInterface::class);
            }
        );

        $url = $this->createMock(UrlInterface::class);
        $url->method('getDirectUrl')->willReturnCallback(
            static fn(string $path) => 'https://example.com/' . ltrim($path, '/')
        );

        $response = $this->createMock(HttpResponse::class);
        $response->method('setRedirect')->willReturnCallback(
            function ($targetUrl) use ($response) {
                $this->redirectedTo = $targetUrl;

                return $response;
            }
        );

        return new BlogRouter($actionFactory, $resource, $config, $historyManager, $url, $response);
    }

    private function request(string $pathInfo): HttpRequest
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getPathInfo')->willReturn($pathInfo);
        $request->method('setModuleName')->willReturnSelf();
        $request->method('setControllerName')->willReturnSelf();
        $request->method('setActionName')->willReturnSelf();
        $request->method('setParam')->willReturnSelf();

        return $request;
    }

    public static function unknownPrefixedSlugs(): array
    {
        return [
            'category' => ['/blog/category/does-not-exist'],
            'tag' => ['/blog/tag/does-not-exist'],
            'author' => ['/blog/author/does-not-exist'],
            'category with pagination' => ['/blog/category/does-not-exist/page/3'],
        ];
    }

    #[DataProvider('unknownPrefixedSlugs')]
    public function testAnUnknownPrefixedSlugFallsThroughInsteadOfForwarding(string $path): void
    {
        $result = $this->router()->match($this->request($path));

        $this->assertNull(
            $result,
            'forwarding here re-enters the router on the same path and ends in a 100 iteration loop'
        );
        $this->assertSame([], $this->created, 'no action may be created for an unknown slug');
    }

    public static function knownPrefixedSlugs(): array
    {
        return [
            'category' => ['/blog/category/hyva', \Panth\Blog\Controller\Category\View::class],
            'tag' => ['/blog/tag/hyva', \Panth\Blog\Controller\Tag\View::class],
            'author' => ['/blog/author/hyva', \Panth\Blog\Controller\Author\View::class],
        ];
    }

    #[DataProvider('knownPrefixedSlugs')]
    public function testAKnownPrefixedSlugStillRoutesToItsController(string $path, string $controller): void
    {
        $result = $this->router(['7'])->match($this->request($path));

        $this->assertNotNull($result, 'a slug that exists must still be routed');
        $this->assertSame([$controller], $this->created);
    }

    public function testARenamedCategoryRedirectsToItsNewPrefixedUrl(): void
    {
        $router = $this->router([], ['category:old-cat' => ['new_url_key' => 'new-cat']]);

        $result = $router->match($this->request('/blog/category/old-cat'));

        $this->assertNotNull($result);
        $this->assertSame('https://example.com/blog/category/new-cat', $this->redirectedTo);
        $this->assertSame(
            [Redirect::class],
            $this->created,
            'a router redirect must return a Redirect action; a Forward un-dispatches and loops'
        );
    }

    public function testARenameLookupIsScopedToItsOwnEntityType(): void
    {
        $router = $this->router([], ['post:old-cat' => ['new_url_key' => 'some-post']]);

        $result = $router->match($this->request('/blog/category/old-cat'));

        $this->assertNull($result, 'a post rename must not resolve a /category/ URL');
        $this->assertNull($this->redirectedTo);
    }

    public function testAnUnknownBareSlugStillFallsThrough(): void
    {
        $this->assertNull($this->router()->match($this->request('/blog/does-not-exist')));
    }
}
