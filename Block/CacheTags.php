<?php
declare(strict_types=1);

namespace Panth\Blog\Block;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\AbstractBlock;
use Panth\Blog\Model\Author;
use Panth\Blog\Model\Category;
use Panth\Blog\Model\Comment;
use Panth\Blog\Model\Post;
use Panth\Blog\Model\Tag;

class CacheTags extends AbstractBlock implements IdentityInterface
{
    public function getIdentities(): array
    {
        return [
            Post::CACHE_TAG,
            Category::CACHE_TAG,
            Tag::CACHE_TAG,
            Author::CACHE_TAG,
            Comment::CACHE_TAG,
        ];
    }

    protected function _toHtml(): string
    {
        return '';
    }
}
