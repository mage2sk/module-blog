<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CommentCaptchaProvider implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'math', 'label' => __('Math Question')],
            ['value' => 'cloudflare_turnstile', 'label' => __('Cloudflare Turnstile')],
            ['value' => 'hcaptcha', 'label' => __('hCaptcha')],
            ['value' => 'recaptcha_v3', 'label' => __('reCAPTCHA v3')],
            ['value' => 'none', 'label' => __('None')],
        ];
    }
}
