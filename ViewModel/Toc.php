<?php
declare(strict_types=1);

namespace Panth\Blog\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;

class Toc implements ArgumentInterface
{
    public function __construct(
        private readonly PostView $postView
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->postView->isTocEnabled();
    }

    public function getItems(): array
    {
        if (!$this->isEnabled()) {
            return [];
        }
        return $this->postView->extractToc();
    }
}
