<?php
declare(strict_types=1);

namespace Panth\Blog\Observer;

use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Framework\App\Area;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Api\Data\CommentInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Helper\Config;
use Psr\Log\LoggerInterface;

class CommentNotification implements ObserverInterface
{
    private const TEMPLATE_ID = 'panth_blog_comment_notification';

    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly TransportBuilder $transportBuilder,
        private readonly UrlInterface $frontUrl,
        private readonly BackendUrl $backendUrl,
        private readonly PostRepositoryInterface $postRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $comment = $observer->getEvent()->getData('object') ?? $observer->getEvent()->getData('comment');
        if (!$comment instanceof CommentInterface) {
            return;
        }

        try {
            $storeId = (int) $this->storeManager->getStore()->getId();

            if (!$this->config->isEnabled($storeId)
                || !$this->config->isCommentsEnabled($storeId)
                || !$this->config->isCommentNotifyAdminEnabled($storeId)
            ) {
                return;
            }

            $origId = method_exists($comment, 'getOrigData')
                ? $comment->getOrigData(CommentInterface::COMMENT_ID)
                : null;
            if ($origId !== null && $origId !== '' && (int) $origId > 0) {
                return;
            }

            $recipient = $this->config->getCommentNotifyRecipient($storeId);
            if ($recipient === '') {
                return;
            }

            $postTitle = (string) __('Untitled post');
            $postUrl = '';
            try {
                $post = $this->postRepository->getById((int) $comment->getPostId());
                $postTitle = (string) $post->getTitle();
                $slug = (string) $post->getUrlKey();
                if ($slug !== '') {
                    $postUrl = $this->frontUrl->getUrl($this->config->getRouteFrontName() . '/' . $slug);
                }
            } catch (\Throwable) {
            }

            $this->transportBuilder
                ->setTemplateIdentifier(self::TEMPLATE_ID)
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
                ->setTemplateVars([
                    'post_title' => $postTitle,
                    'post_url' => $postUrl,
                    'author_name' => (string) $comment->getAuthorName(),
                    'author_email' => (string) $comment->getAuthorEmail(),
                    'content' => (string) $comment->getContent(),
                    'status' => (string) $comment->getStatus(),
                    'moderation_url' => $this->backendUrl->getUrl('panth_blog/comment/index'),
                ])
                ->setFromByScope($this->config->getCommentNotifySenderIdentity($storeId), $storeId)
                ->addTo($recipient)
                ->getTransport()
                ->sendMessage();
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] Comment notification failed: ' . $e->getMessage());
        }
    }
}
