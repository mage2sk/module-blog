<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Adminhtml\Category;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use Panth\Blog\Api\CategoryRepositoryInterface;
use Panth\Blog\Model\ResourceModel\Category\CollectionFactory;

class MassStatus extends Category implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Panth_Blog::category_save';

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly CategoryRepositoryInterface $repository
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $type = (string)$this->getRequest()->getParam('type', '');

        if (!in_array($type, ['enable', 'disable'], true)) {
            $this->messageManager->addErrorMessage(__('Invalid status action.'));
            return $resultRedirect->setPath('*/*/');
        }
        $isActive = $type === 'enable' ? 1 : 0;

        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $count = 0;
        foreach ($collection->getAllIds() as $id) {
            try {
                $category = $this->repository->getById((int)$id);
                $category->setIsActive($isActive);
                $this->repository->save($category);
                $count++;
            } catch (\Throwable $e) {
                $this->messageManager->addErrorMessage(__('Category %1: %2', $id, $e->getMessage()));
            }
        }

        if ($count > 0) {
            $this->messageManager->addSuccessMessage(
                __('A total of %1 category(ies) have been %2.', $count, $isActive ? __('enabled') : __('disabled'))
            );
        }

        return $resultRedirect->setPath('*/*/');
    }
}
