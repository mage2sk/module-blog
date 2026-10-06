<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Adminhtml\Comment;

use Magento\Backend\App\Action;

abstract class Comment extends Action
{
    public const ADMIN_RESOURCE = 'Panth_Blog::comment';
}
