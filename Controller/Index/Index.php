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
use Panth\Blog\Model\Pagination\PageUrlBuilder;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly RequestInterface $request,
        private readonly Registry $registry,
        private readonly ResultFactory $resultFactory,
        private readonly Config $config,
        private readonly UrlInterface $urlBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly PageUrlBuilder $pageUrlBuilder
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->config->isEnabled()) {
            $forward = $this->resultFactory->create(ResultFactory::TYPE_FORWARD);
            $forward->setModule('cms')->setController('noroute')->forward('index');
            return $forward;
        }

        $page = (int) $this->request->getParam('page', 1);
        if ($page < 1) {
            $page = 1;
        }
        $this->registry->register('panth_blog_current_page', $page, true);

        $result = $this->pageFactory->create();

        $title = trim($this->config->getIndexTitle());
        if ($title === '') {
            $title = (string) __('Blog');
        }
        $result->getConfig()->getTitle()->set($title);

        $description = trim($this->config->getIndexDescription());
        if ($description !== '') {
            $result->getConfig()->setDescription($description);
        }

        $this->addBreadcrumbs($result);
        $this->addCanonical($result);
        $this->addPrevNext($result, $page);

        return $result;
    }

    private function addBreadcrumbs(Page $result): void
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
            ]);
        } catch (\Throwable) {
        }
    }

    private function addCanonical(Page $result): void
    {
        try {
            $base = (string) $this->storeManager->getStore()->getBaseUrl();
            $canonical = rtrim($base, '/') . '/' . $this->config->getRouteFrontName();
            $result->getConfig()->addRemotePageAsset(
                $canonical,
                'canonical',
                ['attributes' => ['rel' => 'canonical']],
                'canonical'
            );
        } catch (\Throwable) {
        }
    }

    private function addPrevNext(Page $result, int $page): void
    {
        try {
            $base = (string) $this->storeManager->getStore()->getBaseUrl();
            $root = rtrim($base, '/') . '/' . $this->config->getRouteFrontName();
            if ($page > 1) {
                $prevPage = $page - 1;
                $prev = $this->pageUrlBuilder->build($root, $prevPage);
                $result->getConfig()->addRemotePageAsset(
                    $prev,
                    'link_rel',
                    ['attributes' => ['rel' => 'prev']],
                    'pb_pagination_prev'
                );
            }
            $next = $this->pageUrlBuilder->build($root, $page + 1);
            $result->getConfig()->addRemotePageAsset(
                $next,
                'link_rel',
                ['attributes' => ['rel' => 'next']],
                'pb_pagination_next'
            );
        } catch (\Throwable) {
        }
    }
}
