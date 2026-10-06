<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CitationStyle implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'numbered_footnote', 'label' => __('Numbered Footnote')],
            ['value' => 'inline_link', 'label' => __('Inline Link')],
            ['value' => 'sidebar', 'label' => __('Sidebar')],
        ];
    }
}
