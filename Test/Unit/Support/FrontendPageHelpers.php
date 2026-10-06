<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Support;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Registry;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\View\Page\Config;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Theme\Block\Html\Breadcrumbs;

trait FrontendPageHelpers
{
    protected array $reqParams = [];
    protected array $page = [];
    protected array $registered = [];
    protected ?string $forwardedTo = null;
    protected bool $withBreadcrumbs = true;

    protected function frontRequest(): Http
    {
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnCallback(fn ($k, $d = null) => $this->reqParams[$k] ?? $d);
        return $request;
    }

    protected function frontRegistry(): Registry
    {
        $registry = $this->createStub(Registry::class);
        $registry->method('register')->willReturnCallback(function ($key, $value) {
            $this->registered[$key] = $value;
        });
        return $registry;
    }

    protected function forwardFactory(): ResultFactory
    {
        $forward = $this->createStub(Forward::class);
        $forward->method('setModule')->willReturnSelf();
        $forward->method('setController')->willReturnSelf();
        $forward->method('forward')->willReturnCallback(function ($action) use ($forward) {
            $this->forwardedTo = $action;
            return $forward;
        });
        $factory = $this->createStub(ResultFactory::class);
        $factory->method('create')->willReturn($forward);
        return $factory;
    }

    protected function frontPageFactory(): PageFactory
    {
        $this->page = ['title' => null, 'description' => null, 'robots' => null, 'keywords' => null, 'assets' => [], 'crumbs' => []];

        $title = $this->createStub(Title::class);
        $title->method('set')->willReturnCallback(function ($t) {
            $this->page['title'] = (string) $t;
        });
        $config = $this->createStub(Config::class);
        $config->method('getTitle')->willReturn($title);
        foreach (['setDescription' => 'description', 'setRobots' => 'robots', 'setKeywords' => 'keywords'] as $method => $key) {
            $config->method($method)->willReturnCallback(function ($v) use ($key) {
                $this->page[$key] = $v;
            });
        }
        $config->method('addRemotePageAsset')->willReturnCallback(function ($url, $type, $props, $name) use ($config) {
            $this->page['assets'][$name] = $url;
            return $config;
        });

        $breadcrumbs = $this->createStub(Breadcrumbs::class);
        $breadcrumbs->method('addCrumb')->willReturnCallback(function ($name, $info) use ($breadcrumbs) {
            $this->page['crumbs'][$name] = $info;
            return $breadcrumbs;
        });
        $layout = $this->createStub(LayoutInterface::class);
        $layout->method('getBlock')->willReturnCallback(fn () => $this->withBreadcrumbs ? $breadcrumbs : false);

        $page = $this->createStub(Page::class);
        $page->method('getConfig')->willReturn($config);
        $page->method('getLayout')->willReturn($layout);

        $factory = $this->createStub(PageFactory::class);
        $factory->method('create')->willReturn($page);
        return $factory;
    }
}
