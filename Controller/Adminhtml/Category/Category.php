<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Adminhtml\Category;

use Magento\Backend\App\Action;

abstract class Category extends Action
{
    public const ADMIN_RESOURCE = 'Panth_Blog::category';
}
