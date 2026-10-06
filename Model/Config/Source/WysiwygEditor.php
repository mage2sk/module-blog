<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class WysiwygEditor implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'tinymce', 'label' => __('TinyMCE (Default)')],
            ['value' => 'hyva_cms', 'label' => __('Hyva CMS')],
            ['value' => 'markdown', 'label' => __('Markdown')],
        ];
    }
}
