<?php
declare(strict_types=1);

namespace Panth\Blog\Block\Post;

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Model\ResourceModel\Post as PostResource;

class FaqWidget extends Template
{
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly PostResource $postResource,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getFaqHtml(): string
    {
        $post = $this->getCurrentPost();
        if ($post === null) {
            return '';
        }

        $faqBlockClass = '\\Panth\\Faq\\Block\\Widget\\Faq';
        if (!class_exists($faqBlockClass)) {
            return '';
        }

        try {
            $slug = $this->getPrimaryCategorySlug($post);
            if ($slug === '') {
                return '';
            }
            $block = $this->getLayout()->createBlock(
                $faqBlockClass,
                'panth_blog_faq_' . (int) $post->getPostId()
            );
            $block->setData('category_url_key', $slug);
            $block->setData('limit', 6);
            $block->setData('show_schema', true);
            return (string) $block->toHtml();
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function getCurrentPost(): ?PostInterface
    {
        $candidate = $this->registry->registry('current_panth_blog_post');
        return $candidate instanceof PostInterface ? $candidate : null;
    }

    private function getPrimaryCategorySlug(PostInterface $post): string
    {
        $postId = (int) ($post->getPostId() ?? 0);
        if ($postId <= 0) {
            return '';
        }
        try {
            $primaryId = $this->postResource->getPrimaryCategoryId($postId);
            if ($primaryId === null || $primaryId <= 0) {
                return '';
            }
            $conn = $this->postResource->getConnection();
            $catTable = $this->postResource->getTable('panth_blog_category');
            $select = $conn->select()
                ->from($catTable, 'url_key')
                ->where('category_id = ?', $primaryId)
                ->limit(1);
            return (string) $conn->fetchOne($select);
        } catch (\Throwable) {
            return '';
        }
    }
}
