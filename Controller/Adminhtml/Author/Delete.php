<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Adminhtml\Author;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Panth\Blog\Api\AuthorRepositoryInterface;

class Delete extends Author implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Panth_Blog::author_delete';

    public function __construct(
        Context $context,
        private readonly AuthorRepositoryInterface $repository
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
                $this->messageManager->addSuccessMessage(__('The author has been deleted.'));
            } catch (\Throwable $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }

        return $resultRedirect->setPath('*/*/');
    }
}
