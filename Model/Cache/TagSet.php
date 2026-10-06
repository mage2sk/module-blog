<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Cache;

use Magento\Framework\DataObject\IdentityInterface;

class TagSet implements IdentityInterface
{
    public function __construct(
        private readonly array $tags = []
    ) {
    }

    public function getIdentities(): array
    {
        return $this->tags;
    }
}
