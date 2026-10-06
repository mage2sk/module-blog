<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Adminhtml\Post;

use Magento\Backend\App\Action;

abstract class Post extends Action
{
    public const ADMIN_RESOURCE = 'Panth_Blog::post';
}
