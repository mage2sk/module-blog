<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Tag;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Panth\Blog\Api\Data\TagInterface;
use Panth\Blog\Api\TagRepositoryInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Url\TagUrlBuilder;
use Panth\Blog\Model\Pagination\PageUrlBuilder;

class View implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly RequestInterface $request,
        private readonly Registry $registry,
        private readonly ResultFactory $resultFactory,
        private readonly Config $config,
        private readonly TagRepositoryInterface $tagRepository,
        private readonly UrlInterface $urlBuilder,
        private readonly TagUrlBuilder $tagUrlBuilder,
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
            $tag = $this->tagRepository->getByUrlKey($slug);
        } catch (NoSuchEntityException) {
            return $this->notFound();
        }

        $page = (int) $this->request->getParam('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $this->registry->register('current_panth_blog_tag', $tag, true);
        $this->registry->register('panth_blog_current_page', $page, true);

        $result = $this->pageFactory->create();

        $result->getConfig()->getTitle()->set((string) $tag->getName());

        $description = (string) ($tag->getDescription() ?? '');
        if ($description !== '') {
            $result->getConfig()->setDescription($description);
        }

        $robots = $tag->getMetaRobots();
        if ($robots === '') {
            $robots = 'index,follow';
        }
        $threshold = $this->config->getTagThinThreshold();
        if ($tag->getPostCount() < $threshold) {
            $robots = 'noindex,follow';
        }
        $result->getConfig()->setRobots($robots);

        $this->addBreadcrumbs($result, $tag);
        $this->addCanonical($result, $tag);
        $this->addPrevNext($result, $tag, $page);

        return $result;
    }

    private function notFound(): ResultInterface
    {
        $forward = $this->resultFactory->create(ResultFactory::TYPE_FORWARD);
        $forward->setModule('cms')->setController('noroute')->forward('index');
        return $forward;
    }

    private function addBreadcrumbs(Page $result, TagInterface $tag): void
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
            $label = (string) __('Tag: %1', (string) $tag->getName());
            $breadcrumbs->addCrumb('blog_tag', [
                'label' => $label,
                'title' => $label,
            ]);
        } catch (\Throwable) {
        }
    }

    private function addCanonical(Page $result, TagInterface $tag): void
    {
        try {
            $canonical = $this->tagUrlBuilder->getTagUrl($tag);
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

    private function addPrevNext(Page $result, TagInterface $tag, int $page): void
    {
        try {
            $base = $this->tagUrlBuilder->getTagUrl($tag);
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
