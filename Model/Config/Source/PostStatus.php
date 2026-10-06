<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class PostStatus implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'draft', 'label' => __('Draft')],
            ['value' => 'scheduled', 'label' => __('Scheduled')],
            ['value' => 'published', 'label' => __('Published')],
            ['value' => 'archived', 'label' => __('Archived')],
        ];
    }
}
