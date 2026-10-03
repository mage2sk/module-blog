<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Adminhtml\Category;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Panth\Blog\Api\CategoryRepositoryInterface;
use Panth\Blog\Api\Data\CategoryInterfaceFactory;

class Edit extends Category
{
    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly CategoryRepositoryInterface $repository,
        private readonly CategoryInterfaceFactory $factory
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
                $this->messageManager->addErrorMessage(__('This category no longer exists.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        } else {
            $this->factory->create();
        }

        $resultPage = $this->pageFactory->create();
        $resultPage->setActiveMenu('Panth_Blog::category');
        $resultPage->getConfig()->getTitle()->prepend($id ? __('Edit Category') : __('New Category'));

        return $resultPage;
    }
}
