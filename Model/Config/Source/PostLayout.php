<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class PostLayout implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'default', 'label' => __('Default')],
            ['value' => 'no-sidebar', 'label' => __('No Sidebar')],
            ['value' => 'wide', 'label' => __('Wide')],
            ['value' => 'longform', 'label' => __('Longform')],
        ];
    }
}
