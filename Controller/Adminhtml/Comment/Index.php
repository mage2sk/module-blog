<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Adminhtml\Comment;

use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index extends Comment
{
    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $resultPage = $this->pageFactory->create();
        $resultPage->setActiveMenu('Panth_Blog::comment');
        $resultPage->getConfig()->getTitle()->prepend(__('Blog Comments'));

        return $resultPage;
    }
}
