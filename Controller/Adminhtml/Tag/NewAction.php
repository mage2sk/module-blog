<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Adminhtml\Tag;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\ResultFactory;

class NewAction extends Tag
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
