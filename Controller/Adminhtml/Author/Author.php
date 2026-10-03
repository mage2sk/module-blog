<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Adminhtml\Author;

use Magento\Backend\App\Action;

abstract class Author extends Action
{
    public const ADMIN_RESOURCE = 'Panth_Blog::author';
}
