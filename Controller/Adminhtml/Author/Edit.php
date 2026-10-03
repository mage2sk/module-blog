<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Adminhtml\Author;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Panth\Blog\Api\AuthorRepositoryInterface;
use Panth\Blog\Api\Data\AuthorInterfaceFactory;

class Edit extends Author
{
    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly AuthorRepositoryInterface $repository,
        private readonly AuthorInterfaceFactory $factory
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
                $this->messageManager->addErrorMessage(__('This author no longer exists.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        } else {
            $this->factory->create();
        }

        $resultPage = $this->pageFactory->create();
        $resultPage->setActiveMenu('Panth_Blog::author');
        $resultPage->getConfig()->getTitle()->prepend($id ? __('Edit Author') : __('New Author'));

        return $resultPage;
    }
}
