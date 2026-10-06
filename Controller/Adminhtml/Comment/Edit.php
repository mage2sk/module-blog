<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Adminhtml\Comment;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Panth\Blog\Api\CommentRepositoryInterface;
use Panth\Blog\Api\Data\CommentInterfaceFactory;

class Edit extends Comment
{
    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly CommentRepositoryInterface $repository,
        private readonly CommentInterfaceFactory $factory
    ) {
        parent::__construct($context);
    }

    public function execute(): Page|ResultInterface
    {
        $id = (int)$this->getRequest()->getParam('id');

        if ($id) {
            try {
                $this->repository->getById($id);
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage(__('This comment no longer exists.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        } else {
            $this->factory->create();
        }

        $resultPage = $this->pageFactory->create();
        $resultPage->setActiveMenu('Panth_Blog::comment');
        $resultPage->getConfig()->getTitle()->prepend($id ? __('Edit Comment') : __('New Comment'));

        return $resultPage;
    }
}
