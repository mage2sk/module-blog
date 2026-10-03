<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Adminhtml\Tag;

use Magento\Backend\App\Action;

abstract class Tag extends Action
{
    public const ADMIN_RESOURCE = 'Panth_Blog::tag';
}
