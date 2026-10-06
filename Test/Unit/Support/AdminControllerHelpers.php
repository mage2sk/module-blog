<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Support;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Backend\Model\View\Result\RedirectFactory;
use Magento\Framework\App\Request\Http;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Page\Config;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\PageFactory;

trait AdminControllerHelpers
{
    protected array $params = [];
    protected mixed $postValue = null;
    protected array $redirect = [];
    protected array $messages = [];
    protected array $titles = [];
    protected ?string $activeMenu = null;
    protected ?ResultFactory $resultFactory = null;
    protected ?AuthorizationInterface $authorization = null;

    protected function adminContext(): Context
    {
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnCallback(fn ($key, $default = null) => $this->params[$key] ?? $default);
        $request->method('getPostValue')->willReturnCallback(fn () => $this->postValue);
        $request->method('getFiles')->willReturnCallback(fn ($key) => $this->params['__files'][$key] ?? null);

        $redirect = $this->createStub(Redirect::class);
        $redirect->method('setPath')->willReturnCallback(function ($path, $params = []) use ($redirect) {
            $this->redirect = [$path, $params];
            return $redirect;
        });
        $redirectFactory = $this->createStub(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($redirect);

        $messages = $this->createStub(ManagerInterface::class);
        foreach (['addSuccessMessage' => 'success', 'addErrorMessage' => 'error', 'addExceptionMessage' => 'exception'] as $method => $type) {
            $messages->method($method)->willReturnCallback(function ($first, $second = null) use ($type, $messages) {
                $text = $first instanceof \Throwable ? (string) $second : (string) $first;
                $this->messages[] = [$type, $text];
                return $messages;
            });
        }

        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultRedirectFactory')->willReturn($redirectFactory);
        $context->method('getMessageManager')->willReturn($messages);
        $context->method('getResultFactory')->willReturn($this->resultFactory ?? $this->createStub(ResultFactory::class));
        $context->method('getAuthorization')->willReturn($this->authorization ?? $this->createStub(AuthorizationInterface::class));
        return $context;
    }

    protected function pageFactory(): PageFactory
    {
        $title = $this->createStub(Title::class);
        $title->method('prepend')->willReturnCallback(function ($value) {
            $this->titles[] = (string) $value;
        });
        $config = $this->createStub(Config::class);
        $config->method('getTitle')->willReturn($title);
        $page = $this->createStub(Page::class);
        $page->method('getConfig')->willReturn($config);
        $page->method('setActiveMenu')->willReturnCallback(function ($menu) use ($page) {
            $this->activeMenu = $menu;
            return $page;
        });
        $factory = $this->createStub(PageFactory::class);
        $factory->method('create')->willReturn($page);
        return $factory;
    }

    protected function messagesOf(string $type): array
    {
        return array_values(array_map(
            static fn ($m) => $m[1],
            array_filter($this->messages, static fn ($m) => $m[0] === $type)
        ));
    }
}
