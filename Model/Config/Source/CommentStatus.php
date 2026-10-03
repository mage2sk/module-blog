<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CommentStatus implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'pending', 'label' => __('Pending')],
            ['value' => 'approved', 'label' => __('Approved')],
            ['value' => 'spam', 'label' => __('Spam')],
            ['value' => 'trash', 'label' => __('Trash')],
        ];
    }
}
