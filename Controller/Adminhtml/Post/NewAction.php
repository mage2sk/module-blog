<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Adminhtml\Post;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\ResultFactory;

class NewAction extends Post
{
    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    public function execute(): Forward
    {
        $forward = $this->resultFactory->create(ResultFactory::TYPE_FORWARD);
        return $forward->forward('edit');
    }
}
