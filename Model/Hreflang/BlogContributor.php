<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Hreflang;

class BlogContributor
{
    public function __construct()
    {
    }

    public function getAlternates(int $postId): array
    {
        unset($postId);
        return [];
    }
}
