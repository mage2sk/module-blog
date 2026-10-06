<?php
declare(strict_types=1);

namespace Panth\Blog\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Panth\Blog\Api\Data\PostInterface;

class RelatedPosts implements ArgumentInterface
{
    public function __construct(
        private readonly PostView $postView
    ) {
    }

    public function getItems(): array
    {
        return $this->postView->getRelatedPosts();
    }

    public function hasItems(): bool
    {
        return $this->getItems() !== [];
    }
}
