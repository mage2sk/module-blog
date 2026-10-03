<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Post;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Exception\LocalizedException;
use Panth\Blog\Api\AuthorRepositoryInterface;
use Panth\Blog\Api\CategoryRepositoryInterface;
use Panth\Blog\Api\Data\AuthorInterface;
use Panth\Blog\Api\Data\CategoryInterface;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\ResourceModel\Post as PostResource;
use Panth\Blog\Model\Url\CategoryUrlBuilder;
use Panth\Blog\Model\Url\PostUrlBuilder;
use Panth\Blog\Model\StoreVisibility;

class View implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly RequestInterface $request,
        private readonly Registry $registry,
        private readonly ResultFactory $resultFactory,
        private readonly Config $config,
        private readonly PostRepositoryInterface $postRepository,
        private readonly UrlInterface $urlBuilder,
        private readonly PostResource $postResource,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly CategoryUrlBuilder $categoryUrlBuilder,
        private readonly PostUrlBuilder $postUrlBuilder,
        private readonly AuthorRepositoryInterface $authorRepository,
        private readonly StoreVisibility $storeVisibility
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
            $post = $this->postRepository->getByUrlKey($slug);
        } catch (NoSuchEntityException) {
            return $this->notFound();
        }

        if ($post->getStatus() !== PostInterface::STATUS_PUBLISHED
            || !$this->storeVisibility->isPostVisible((int) $post->getPostId())
        ) {
            return $this->notFound();
        }

        $this->registry->register('current_panth_blog_post', $post, true);

        $result = $this->pageFactory->create();

        $primary = $this->resolvePrimaryCategory($post);
        $title = $this->buildDocumentTitle($post, $primary);
        $result->getConfig()->getTitle()->set($title);

        $description = $post->getMetaDescription();
        if ($description === null || trim($description) === '') {
            $description = (string) $post->getShortDescription();
        }
        if ($description === '') {
            $description = $this->config->getDefaultMetaDescription();
        }
        $result->getConfig()->setDescription((string) $description);

        $result->getConfig()->setKeywords((string) ($post->getMetaKeywords() ?? ''));

        $robots = $post->getMetaRobots();
        if ($robots === '') {
            $robots = 'index,follow';
        }
        $result->getConfig()->setRobots($robots);

        $this->addBreadcrumbs($result, $post, $primary);
        $this->addCanonical($result, $post);

        return $result;
    }

    private function buildDocumentTitle(PostInterface $post, ?CategoryInterface $primary): string
    {
        $template = trim((string) $this->config->getTitleTemplate());
        $postTitle = (string) ($post->getTitle() ?? '');
        $metaTitle = trim((string) ($post->getMetaTitle() ?? ''));

        if ($template === '' || strpos($template, '{{') === false) {
            return $metaTitle !== '' ? $metaTitle : $postTitle;
        }

        $authorName = '';
        $authorId = (int) ($post->getAuthorId() ?? 0);
        if ($authorId > 0) {
            try {
                $author = $this->authorRepository->getById($authorId);
                if ($author instanceof AuthorInterface) {
                    $authorName = (string) ($author->getDisplayName() ?? '');
                }
            } catch (\Throwable) {
                $authorName = '';
            }
        }

        $categoryName = $primary !== null ? (string) ($primary->getName() ?? '') : '';

        $tokens = [
            '{{post.title}}'          => $postTitle,
            '{{post.meta_title}}'     => $metaTitle !== '' ? $metaTitle : $postTitle,
            '{{post.category}}'       => $categoryName,
            '{{post.author}}'         => $authorName,
        ];

        $rendered = strtr($template, $tokens);

        $rendered = preg_replace('/\s*([|\-–—])\s*\1\s*/u', ' $1 ', (string) $rendered) ?? $rendered;
        $rendered = trim(preg_replace('/\s{2,}/u', ' ', (string) $rendered) ?? $rendered);
        $rendered = trim($rendered, " \t|-–—");
        if ($rendered === '') {
            return $metaTitle !== '' ? $metaTitle : $postTitle;
        }
        return $rendered;
    }

    private function notFound(): ResultInterface
    {
        $forward = $this->resultFactory->create(ResultFactory::TYPE_FORWARD);
        $forward->setModule('cms')->setController('noroute')->forward('index');
        return $forward;
    }

    private function resolvePrimaryCategory(PostInterface $post): ?CategoryInterface
    {
        if ($post->getPostId() === null) {
            return null;
        }
        try {
            $id = $this->postResource->getPrimaryCategoryId((int) $post->getPostId());
            if ($id === null || $id <= 0) {
                return null;
            }
            return $this->categoryRepository->getById($id);
        } catch (\Throwable) {
            return null;
        }
    }

    private function addBreadcrumbs(Page $result, PostInterface $post, ?CategoryInterface $primary): void
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
            if ($primary !== null) {
                try {
                    $catUrl = $this->categoryUrlBuilder->getCategoryUrl($primary);
                } catch (\Throwable) {
                    $catUrl = '';
                }
                $breadcrumbs->addCrumb('blog_category', [
                    'label' => (string) $primary->getName(),
                    'title' => (string) $primary->getName(),
                    'link'  => $catUrl !== '' ? $catUrl : null,
                ]);
            }
            $breadcrumbs->addCrumb('blog_post', [
                'label' => (string) $post->getTitle(),
                'title' => (string) $post->getTitle(),
            ]);
        } catch (\Throwable) {
        }
    }

    private function addCanonical(Page $result, PostInterface $post): void
    {
        try {
            $override = (string) ($post->getCanonicalUrl() ?? '');
            $canonical = $override !== '' ? $override : $this->postUrlBuilder->getPostUrl($post);
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
}
