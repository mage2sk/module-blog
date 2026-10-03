<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Author;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Panth\Blog\Api\AuthorRepositoryInterface;
use Panth\Blog\Api\Data\AuthorInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Url\AuthorUrlBuilder;
use Panth\Blog\Model\Pagination\PageUrlBuilder;

class View implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly RequestInterface $request,
        private readonly Registry $registry,
        private readonly ResultFactory $resultFactory,
        private readonly Config $config,
        private readonly AuthorRepositoryInterface $authorRepository,
        private readonly UrlInterface $urlBuilder,
        private readonly AuthorUrlBuilder $authorUrlBuilder,
        private readonly PageUrlBuilder $pageUrlBuilder
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
            $author = $this->authorRepository->getByUrlKey($slug);
        } catch (NoSuchEntityException) {
            return $this->notFound();
        }

        if (!$author->getIsActive()) {
            return $this->notFound();
        }

        $page = (int) $this->request->getParam('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $this->registry->register('current_panth_blog_author', $author, true);
        $this->registry->register('panth_blog_current_page', $page, true);

        $result = $this->pageFactory->create();

        $result->getConfig()->getTitle()->set((string) $author->getDisplayName());

        $description = (string) ($author->getShortBio() ?? '');
        if ($description !== '') {
            $result->getConfig()->setDescription($description);
        }

        $result->getConfig()->setRobots('index,follow');

        $this->addBreadcrumbs($result, $author);
        $this->addCanonical($result, $author);
        $this->addPrevNext($result, $author, $page);

        return $result;
    }

    private function notFound(): ResultInterface
    {
        $forward = $this->resultFactory->create(ResultFactory::TYPE_FORWARD);
        $forward->setModule('cms')->setController('noroute')->forward('index');
        return $forward;
    }

    private function addBreadcrumbs(Page $result, AuthorInterface $author): void
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
            $label = (string) __('Author: %1', (string) $author->getDisplayName());
            $breadcrumbs->addCrumb('blog_author', [
                'label' => $label,
                'title' => $label,
            ]);
        } catch (\Throwable) {
        }
    }

    private function addCanonical(Page $result, AuthorInterface $author): void
    {
        try {
            $canonical = $this->authorUrlBuilder->getAuthorUrl($author);
            if ($canonical === '') {
                return;
            }
            $result->getConfig()->addRemotePageAsset(
                $canonical,
                'canonical',
                ['attributes' => ['rel' => 'canonical']],
                'canonical'
            );
        } catch (\Throwable) {
        }
    }

    private function addPrevNext(Page $result, AuthorInterface $author, int $page): void
    {
        try {
            $base = $this->authorUrlBuilder->getAuthorUrl($author);
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
            $next = $this->pageUrlBuilder->build($base, $page + 1);
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
