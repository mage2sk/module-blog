<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Cache\Type;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;

class LlmsTxt extends TagScope
{
    public const TYPE_IDENTIFIER = 'panth_blog_llmstxt';

    public const CACHE_TAG = 'PANTH_BLOG_LLMSTXT';

    public function __construct(FrontendPool $cachePool)
    {
        parent::__construct($cachePool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);
    }
}
