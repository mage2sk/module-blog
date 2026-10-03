<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Adminhtml\Comment;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use Panth\Blog\Api\CommentRepositoryInterface;
use Panth\Blog\Model\ResourceModel\Comment\CollectionFactory;

class MassDelete extends Comment implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly CommentRepositoryInterface $repository
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $count = 0;
        foreach ($collection as $item) {
            try {
                $this->repository->delete($item);
                $count++;
            } catch (\Throwable $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }

        if ($count > 0) {
            $this->messageManager->addSuccessMessage(
                __('A total of %1 comment(s) have been deleted.', $count)
            );
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
