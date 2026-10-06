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
use Panth\Blog\ViewModel\BlogIndex as ListingViewModel;

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
        private readonly PageUrlBuilder $pageUrlBuilder,
        private readonly ListingViewModel $listing
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->config->isEnabled()) {
            return $this->notFound();
        }

        $page = (int) $this->request->getParam('page', 1);
        if ($page < 1) {
            $page = 1;
        }
        $this->registry->register('panth_blog_current_page', $page, true);

        $totalPages = $this->pageUrlBuilder->totalPages(
            $this->listing->getTotalPosts(),
            $this->listing->getPostsPerPage()
        );
        if ($page > $totalPages) {
            return $this->notFound();
        }

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
        $this->addCanonical($result, $page);
        $this->addPrevNext($result, $page, $totalPages);

        return $result;
    }

    private function notFound(): ResultInterface
    {
        $forward = $this->resultFactory->create(ResultFactory::TYPE_FORWARD);
        $forward->setModule('cms')->setController('noroute')->forward('index');
        return $forward;
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

    private function addCanonical(Page $result, int $page): void
    {
        try {
            $base = (string) $this->storeManager->getStore()->getBaseUrl();
            $canonical = rtrim($base, '/') . '/' . $this->config->getRouteFrontName();
            $result->getConfig()->addRemotePageAsset(
                $this->pageUrlBuilder->build($canonical, $page),
                'canonical',
                ['attributes' => ['rel' => 'canonical']],
                'canonical'
            );
        } catch (\Throwable) {
        }
    }

    private function addPrevNext(Page $result, int $page, int $totalPages): void
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
            if ($page < $totalPages) {
                $result->getConfig()->addRemotePageAsset(
                    $this->pageUrlBuilder->build($root, $page + 1),
                    'link_rel',
                    ['attributes' => ['rel' => 'next']],
                    'pb_pagination_next'
                );
            }
        } catch (\Throwable) {
        }
    }
}
