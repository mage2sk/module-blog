<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CommentAllowFor implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'everyone', 'label' => __('Everyone')],
            ['value' => 'registered_only', 'label' => __('Registered Customers Only')],
            ['value' => 'nobody', 'label' => __('Nobody (Read-only)')],
        ];
    }
}
