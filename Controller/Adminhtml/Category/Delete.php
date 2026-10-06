<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Adminhtml\Category;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Panth\Blog\Api\CategoryRepositoryInterface;

class Delete extends Category implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Panth_Blog::category_delete';

    public function __construct(
        Context $context,
        private readonly CategoryRepositoryInterface $repository
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = (int)$this->getRequest()->getParam('id');

        if ($id) {
            try {
                $this->repository->deleteById($id);
                $this->messageManager->addSuccessMessage(__('The category has been deleted.'));
            } catch (\Throwable $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }

        return $resultRedirect->setPath('*/*/');
    }
}
