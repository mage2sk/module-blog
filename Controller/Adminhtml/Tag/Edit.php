<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Adminhtml\Tag;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Panth\Blog\Api\Data\TagInterfaceFactory;
use Panth\Blog\Api\TagRepositoryInterface;

class Edit extends Tag
{
    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly TagRepositoryInterface $repository,
        private readonly TagInterfaceFactory $factory
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
                $this->messageManager->addErrorMessage(__('This tag no longer exists.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        } else {
            $this->factory->create();
        }

        $resultPage = $this->pageFactory->create();
        $resultPage->setActiveMenu('Panth_Blog::tag');
        $resultPage->getConfig()->getTitle()->prepend($id ? __('Edit Tag') : __('New Tag'));

        return $resultPage;
    }
}
