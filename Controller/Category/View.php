<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Category;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Panth\Blog\Api\CategoryRepositoryInterface;
use Panth\Blog\Api\Data\CategoryInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Url\CategoryUrlBuilder;
use Panth\Blog\Model\Pagination\PageUrlBuilder;
use Panth\Blog\ViewModel\CategoryView as ListingViewModel;
use Panth\Blog\Model\StoreVisibility;

class View implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly RequestInterface $request,
        private readonly Registry $registry,
        private readonly ResultFactory $resultFactory,
        private readonly Config $config,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly UrlInterface $urlBuilder,
        private readonly CategoryUrlBuilder $categoryUrlBuilder,
        private readonly PageUrlBuilder $pageUrlBuilder,
        private readonly StoreVisibility $storeVisibility,
        private readonly ListingViewModel $listing
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->config->isEnabled()) {
            return $this->notFound();
        }

        $slug = trim((string) $this->request->getParam('slug', ''));
        if ($slug === '') {
            return $this->notFound();
        }

        try {
            $category = $this->categoryRepository->getByUrlKey($slug);
        } catch (NoSuchEntityException) {
            return $this->notFound();
        }

        if (!$category->getIsActive()
            || !$this->storeVisibility->isCategoryVisible((int) $category->getCategoryId())
        ) {
            return $this->notFound();
        }

        $page = (int) $this->request->getParam('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $this->registry->register('current_panth_blog_category', $category, true);
        $this->registry->register('panth_blog_current_page', $page, true);

        $totalPages = $this->pageUrlBuilder->totalPages(
            $this->listing->getTotalPosts(),
            $this->listing->getPostsPerPage()
        );
        if ($page > $totalPages) {
            return $this->notFound();
        }

        $result = $this->pageFactory->create();

        $title = $category->getMetaTitle();
        if ($title === null || trim($title) === '') {
            $title = $category->getName();
        }
        $result->getConfig()->getTitle()->set((string) $title);

        $description = (string) ($category->getMetaDescription() ?? '');
        if ($description !== '') {
            $result->getConfig()->setDescription($description);
        }

        $robots = $category->getMetaRobots();
        if ($robots === '') {
            $robots = 'index,follow';
        }
        $result->getConfig()->setRobots($robots);

        $this->addBreadcrumbs($result, $category);
        $this->addCanonical($result, $category, $page);
        $this->addPrevNext($result, $category, $page, $totalPages);

        return $result;
    }

    private function notFound(): ResultInterface
    {
        $forward = $this->resultFactory->create(ResultFactory::TYPE_FORWARD);
        $forward->setModule('cms')->setController('noroute')->forward('index');
        return $forward;
    }

    private function addBreadcrumbs(Page $result, CategoryInterface $category): void
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

            $parentId = (int) ($category->getParentId() ?? 0);
            if ($parentId > 0) {
                try {
                    $parent = $this->categoryRepository->getById($parentId);
                    if ($parent->getIsActive()) {
                        $breadcrumbs->addCrumb('blog_parent_category', [
                            'label' => (string) $parent->getName(),
                            'title' => (string) $parent->getName(),
                            'link'  => $this->categoryUrlBuilder->getCategoryUrl($parent),
                        ]);
                    }
                } catch (\Throwable) {
                }
            }

            $breadcrumbs->addCrumb('blog_category', [
                'label' => (string) $category->getName(),
                'title' => (string) $category->getName(),
            ]);
        } catch (\Throwable) {
        }
    }

    private function addCanonical(Page $result, CategoryInterface $category, int $page): void
    {
        try {
            $canonical = $this->categoryUrlBuilder->getCategoryUrl($category);
            if ($canonical === '') {
                return;
            }
            $result->getConfig()->addRemotePageAsset(
                $this->pageUrlBuilder->build($canonical, $page),
                'canonical',
                ['attributes' => ['rel' => 'canonical']],
                'canonical'
            );
        } catch (\Throwable) {
        }
    }

    private function addPrevNext(Page $result, CategoryInterface $category, int $page, int $totalPages): void
    {
        try {
            $base = $this->categoryUrlBuilder->getCategoryUrl($category);
            if ($base === '') {
                return;
            }
            if ($page > 1) {
                $prevPage = $page - 1;
                $prev = $this->pageUrlBuilder->build($base, $prevPage);
                $result->getConfig()->addRemotePageAsset(
                    $prev,
                    'link_rel',
                    ['attributes' => ['rel' => 'prev']],
                    'pb_pagination_prev'
                );
            }
            if ($page < $totalPages) {
                $result->getConfig()->addRemotePageAsset(
                    $this->pageUrlBuilder->build($base, $page + 1),
                    'link_rel',
                    ['attributes' => ['rel' => 'next']],
                    'pb_pagination_next'
                );
            }
        } catch (\Throwable) {
        }
    }
}
