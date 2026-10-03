<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Adminhtml\Post;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Panth\Blog\Api\Data\PostInterfaceFactory;
use Panth\Blog\Api\PostRepositoryInterface;

class Edit extends Post
{
    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly PostRepositoryInterface $repository,
        private readonly PostInterfaceFactory $factory
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
                $this->messageManager->addErrorMessage(__('This post no longer exists.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        } else {
            $this->factory->create();
        }

        $resultPage = $this->pageFactory->create();
        $resultPage->setActiveMenu('Panth_Blog::post');
        $resultPage->getConfig()->getTitle()->prepend($id ? __('Edit Post') : __('New Post'));

        return $resultPage;
    }
}
