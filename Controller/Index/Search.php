<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Helper\Config;

class Search implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly RequestInterface $request,
        private readonly Registry $registry,
        private readonly ResultFactory $resultFactory,
        private readonly Config $config,
        private readonly UrlInterface $urlBuilder,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->config->isEnabled()) {
            $forward = $this->resultFactory->create(ResultFactory::TYPE_FORWARD);
            $forward->setModule('cms')->setController('noroute')->forward('index');
            return $forward;
        }

        $query = trim((string) $this->request->getParam('q', ''));
        $this->registry->register('panth_blog_search_query', $query, true);

        $page = (int) $this->request->getParam('page', 1);
        if ($page < 1) {
            $page = 1;
        }
        $this->registry->register('panth_blog_current_page', $page, true);

        $result = $this->pageFactory->create();

        if ($query !== '') {
            $result->getConfig()->getTitle()->set(
                (string) __('Search results for "%1"', $query)
            );
        } else {
            $result->getConfig()->getTitle()->set((string) __('Search the blog'));
        }
        $result->getConfig()->setRobots('noindex,follow');

        $this->addBreadcrumbs($result, $query);
        $this->addCanonical($result, $query);

        return $result;
    }

    private function addBreadcrumbs(Page $result, string $query): void
    {
        try {
            $breadcrumbs = $result->getLayout()->getBlock('breadcrumbs');
            if (!$breadcrumbs) {
                return;
            }
            $breadcrumbs->addCrumb('home', [
                'label' => __('Home'),
                'title' => __('Home'),
                'link'  => $this->urlBuilder->getUrl(''),
            ]);
            $breadcrumbs->addCrumb('blog', [
                'label' => __('Blog'),
                'title' => __('Blog'),
                'link'  => $this->urlBuilder->getUrl($this->config->getRouteFrontName()),
            ]);
            $label = $query !== ''
                ? (string) __('Search: %1', $query)
                : (string) __('Search');
            $breadcrumbs->addCrumb('blog_search', [
                'label' => $label,
                'title' => $label,
            ]);
        } catch (\Throwable) {
        }
    }

    private function addCanonical(Page $result, string $query): void
    {
        try {
            $base = (string) $this->storeManager->getStore()->getBaseUrl();
            $canonical = rtrim($base, '/') . '/' . $this->config->getRouteFrontName() . '/search';
            $result->getConfig()->addRemotePageAsset(
                $canonical,
                'canonical',
                ['attributes' => ['rel' => 'canonical']],
                'canonical'
            );
        } catch (\Throwable) {
        }
    }
}
