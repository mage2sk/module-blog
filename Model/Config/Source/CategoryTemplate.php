<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CategoryTemplate implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'grid', 'label' => __('Grid')],
            ['value' => 'list', 'label' => __('List')],
            ['value' => 'magazine', 'label' => __('Magazine')],
        ];
    }
}
