<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Comment;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\RequestInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\UrlInterface;
use Panth\Blog\Api\CommentRepositoryInterface;
use Panth\Blog\Api\Data\CommentInterface;
use Panth\Blog\Api\Data\CommentInterfaceFactory;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Comment\Captcha;
use Panth\Blog\Model\ResourceModel\Comment as CommentResource;
use Panth\Blog\Model\StoreVisibility;

class Submit implements HttpPostActionInterface
{
    private readonly CommentResource $commentResource;

    public function __construct(
        private readonly ResultFactory $resultFactory,
        private readonly RequestInterface $request,
        private readonly Config $config,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly PostRepositoryInterface $postRepository,
        private readonly CommentRepositoryInterface $commentRepository,
        private readonly CommentInterfaceFactory $commentFactory,
        private readonly MessageManagerInterface $messageManager,
        private readonly UrlInterface $urlBuilder,
        private readonly CustomerSession $customerSession,
        private readonly Captcha $captcha,
        private readonly StoreVisibility $storeVisibility,
        ?CommentResource $commentResource = null
    ) {
        $this->commentResource = $commentResource
            ?? ObjectManager::getInstance()->get(CommentResource::class);
    }

    public function execute(): ResultInterface
    {
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        if (!$this->config->isEnabled()) {
            $redirect->setPath('noroute');
            return $redirect;
        }

        $postSlug = trim((string) $this->request->getParam('post_slug', ''));
        $postUrl = $postSlug !== ''
            ? $this->urlBuilder->getUrl($this->config->getRouteFrontName() . '/' . $postSlug)
            : $this->urlBuilder->getUrl($this->config->getRouteFrontName());

        if (!$this->config->isCommentsEnabled()) {
            $redirect->setUrl($postUrl);
            return $redirect;
        }

        $allowFor = $this->config->getCommentsAllowFor();
        $isLoggedIn = $this->customerSession->isLoggedIn();
        if ($allowFor === 'nobody'
            || ($allowFor === 'registered_only' && !$isLoggedIn)
        ) {
            $this->messageManager->addErrorMessage((string) __('Comment submission is not permitted.'));
            $redirect->setUrl($postUrl);
            return $redirect;
        }

        if (!$this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage((string) __('Invalid form key. Please try again.'));
            $redirect->setUrl($postUrl);
            return $redirect;
        }

        if (!$this->captcha->verify($this->request, (string) $this->request->getClientIp())) {
            $this->messageManager->addErrorMessage(
                (string) __('Please complete the verification check and try again.')
            );
            $redirect->setUrl($postUrl);
            return $redirect;
        }

        if ($postSlug === '') {
            $this->messageManager->addErrorMessage((string) __('Missing post reference.'));
            $redirect->setUrl($postUrl);
            return $redirect;
        }

        try {
            $post = $this->postRepository->getByUrlKey($postSlug);
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage((string) __('Post not found.'));
            $redirect->setUrl($postUrl);
            return $redirect;
        }

        if ($post->getStatus() !== PostInterface::STATUS_PUBLISHED
            || !$post->getEnableComments()
            || !$this->storeVisibility->isPostVisible((int) $post->getPostId())
        ) {
            $this->messageManager->addErrorMessage((string) __('Comments are closed for this post.'));
            $redirect->setUrl($postUrl);
            return $redirect;
        }

        $authorName = trim((string) $this->request->getParam('author_name', ''));
        $authorEmail = trim((string) $this->request->getParam('author_email', ''));
        $authorWebsite = trim((string) $this->request->getParam('author_website', ''));
        $content = trim((string) $this->request->getParam('content', ''));
        $parentId = (int) $this->request->getParam('parent_id', 0);

        if ($authorName === '' || $content === '') {
            $this->messageManager->addErrorMessage((string) __('Please fill in your name and comment.'));
            $redirect->setUrl($postUrl);
            return $redirect;
        }

        if ($authorEmail !== '' && !filter_var($authorEmail, FILTER_VALIDATE_EMAIL)) {
            $this->messageManager->addErrorMessage((string) __('Please provide a valid email address.'));
            $redirect->setUrl($postUrl);
            return $redirect;
        }

        $clientIp = (string) $this->request->getClientIp();
        $maxPerHour = $this->config->getCommentsMaxPerHourPerIp();
        if ($maxPerHour > 0 && $this->commentResource->countRecentByIp($clientIp, 3600) >= $maxPerHour) {
            $this->messageManager->addErrorMessage(
                (string) __('Too many comments from your address. Please try again later.')
            );
            $redirect->setUrl($postUrl);
            return $redirect;
        }

        if (!$this->config->isCommentsThreadingEnabled()) {
            $parentId = 0;
        }

        if ($parentId > 0) {
            try {
                $parent = $this->commentRepository->getById($parentId);
                if ((int) $parent->getPostId() !== (int) $post->getPostId()) {
                    $parentId = 0;
                }
            } catch (NoSuchEntityException) {
                $parentId = 0;
            }
        }

        if ($this->config->isCommentsNofollowExternal()) {
            $content = $this->injectNofollow($content);
        }

        $status = ($isLoggedIn && $this->config->isCommentsAutoApproveRegistered())
            ? CommentInterface::STATUS_APPROVED
            : CommentInterface::STATUS_PENDING;

        $comment = $this->commentFactory->create();
        $comment->setPostId((int) $post->getPostId());
        $comment->setParentId($parentId > 0 ? $parentId : null);
        $comment->setAuthorName($authorName);
        $comment->setAuthorEmail($authorEmail !== '' ? $authorEmail : null);
        $comment->setAuthorWebsite($authorWebsite !== '' ? $authorWebsite : null);
        $comment->setContent($content);
        $comment->setStatus($status);
        $comment->setIp($clientIp !== '' ? substr($clientIp, 0, 45) : null);
        $userAgent = (string) $this->request->getServer('HTTP_USER_AGENT', '');
        $comment->setUserAgent($userAgent !== '' ? $userAgent : null);

        try {
            $this->commentRepository->save($comment);
            $message = $status === CommentInterface::STATUS_APPROVED
                ? (string) __('Thanks, your comment has been posted.')
                : (string) __('Thanks, your comment is pending moderation.');
            $this->messageManager->addSuccessMessage($message);
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage((string) __('Unable to submit comment.'));
        }

        $redirect->setUrl($postUrl);
        return $redirect;
    }

    private function injectNofollow(string $content): string
    {
        return (string) preg_replace_callback(
            '#<a\b([^>]*?)href=("|\')(https?://[^"\']+)("|\')([^>]*)>#i',
            static function (array $m): string {
                $href = $m[3];
                $rest = $m[1] . 'href=' . $m[2] . $href . $m[4] . $m[5];
                if (stripos($rest, 'rel=') !== false) {
                    return '<a ' . preg_replace(
                        '/rel=("|\')([^"\']*)\\1/i',
                        'rel="$2 nofollow"',
                        $rest,
                        1
                    ) . '>';
                }
                return '<a ' . trim($rest) . ' rel="nofollow">';
            },
            $content
        );
    }
}
