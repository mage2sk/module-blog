<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class RelatedPostsStrategy implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'manual_only', 'label' => __('Manual Only')],
            ['value' => 'auto_only', 'label' => __('Auto Only')],
            ['value' => 'manual_then_auto', 'label' => __('Manual, then Auto-fill')],
        ];
    }
}
