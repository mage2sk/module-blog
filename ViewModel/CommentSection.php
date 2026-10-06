<?php
declare(strict_types=1);

namespace Panth\Blog\ViewModel;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Panth\Blog\Api\CommentRepositoryInterface;
use Panth\Blog\Api\Data\CommentInterface;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Comment\Captcha;
use Psr\Log\LoggerInterface;

class CommentSection implements ArgumentInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly CommentRepositoryInterface $commentRepository,
        private readonly SearchCriteriaBuilder $criteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        private readonly CustomerSession $customerSession,
        private readonly FormKey $formKey,
        private readonly UrlInterface $urlBuilder,
        private readonly LoggerInterface $logger,
        private readonly Captcha $captcha
    ) {
    }

    public function isVisible(?PostInterface $post): bool
    {
        if ($post === null) {
            return false;
        }
        if (!$this->config->isEnabled() || !$this->config->isCommentsEnabled()) {
            return false;
        }

        return (bool) $post->getEnableComments();
    }

    public function canPost(): bool
    {
        $allow = $this->config->getCommentsAllowFor();
        if ($allow === 'nobody') {
            return false;
        }
        if ($allow === 'registered_only') {
            return $this->customerSession->isLoggedIn();
        }

        return true;
    }

    public function getReason(): string
    {
        $allow = $this->config->getCommentsAllowFor();
        if ($allow === 'nobody') {
            return (string) __('Comments are closed for this post.');
        }
        if ($allow === 'registered_only' && !$this->customerSession->isLoggedIn()) {
            return (string) __('Sign in to leave a comment.');
        }

        return '';
    }

    public function getApproved(int $postId): array
    {
        if ($postId <= 0) {
            return [];
        }
        try {
            $sort = $this->sortOrderBuilder
                ->setField(CommentInterface::CREATED_AT)
                ->setDirection('ASC')
                ->create();
            $criteria = $this->criteriaBuilder
                ->addFilter(CommentInterface::POST_ID, $postId)
                ->addFilter(CommentInterface::STATUS, CommentInterface::STATUS_APPROVED)
                ->addSortOrder($sort)
                ->create();

            return (array) $this->commentRepository->getList($criteria)->getItems();
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] CommentSection::getApproved failed: ' . $e->getMessage());

            return [];
        }
    }

    public function getFormKey(): string
    {
        return (string) $this->formKey->getFormKey();
    }

    public function getSubmitUrl(): string
    {
        return $this->urlBuilder->getUrl($this->config->getRouteFrontName() . '/comment/submit');
    }

    public function getThreaded(array $comments): array
    {
        $threading = $this->isThreadingEnabled();
        $ids = [];
        foreach ($comments as $comment) {
            $ids[(int) $comment->getCommentId()] = true;
        }
        $children = [];
        $roots = [];
        foreach ($comments as $comment) {
            $parentId = (int) $comment->getParentId();
            if ($threading && $parentId > 0 && isset($ids[$parentId])
                && $parentId !== (int) $comment->getCommentId()
            ) {
                $children[$parentId][] = $comment;
                continue;
            }
            $roots[] = $comment;
        }

        return $this->buildNodes($roots, $children, []);
    }

    private function buildNodes(array $comments, array $children, array $seen): array
    {
        $nodes = [];
        foreach ($comments as $comment) {
            $id = (int) $comment->getCommentId();
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $nodes[] = [
                'comment' => $comment,
                'replies' => $this->buildNodes($children[$id] ?? [], $children, $seen),
            ];
        }

        return $nodes;
    }

    public function isThreadingEnabled(): bool
    {
        return $this->config->isCommentsThreadingEnabled();
    }

    public function getCaptchaProvider(): string
    {
        return $this->captcha->getProvider();
    }

    public function getCaptchaSiteKey(): string
    {
        return $this->captcha->getSiteKey();
    }

    public function getMathChallenge(): array
    {
        return $this->captcha->createMathChallenge();
    }
}
