<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Adminhtml\Comment;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use Panth\Blog\Api\CommentRepositoryInterface;
use Panth\Blog\Api\Data\CommentInterface;
use Panth\Blog\Model\ResourceModel\Comment\CollectionFactory;

class MassStatus extends Comment implements HttpPostActionInterface
{
    private const ALLOWED_STATUSES = [
        CommentInterface::STATUS_PENDING,
        CommentInterface::STATUS_APPROVED,
        CommentInterface::STATUS_SPAM,
        CommentInterface::STATUS_TRASH,
    ];

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
        $resultRedirect = $this->resultRedirectFactory->create();
        $status = (string)$this->getRequest()->getParam('status', '');

        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            $this->messageManager->addErrorMessage(__('Invalid status value.'));
            return $resultRedirect->setPath('*/*/');
        }

        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $count = 0;
        foreach ($collection->getAllIds() as $id) {
            try {
                $comment = $this->repository->getById((int)$id);
                $comment->setStatus($status);
                $this->repository->save($comment);
                $count++;
            } catch (\Throwable $e) {
                $this->messageManager->addErrorMessage(__('Comment %1: %2', $id, $e->getMessage()));
            }
        }

        if ($count > 0) {
            $this->messageManager->addSuccessMessage(
                __('A total of %1 comment(s) updated to %2.', $count, $status)
            );
        }

        return $resultRedirect->setPath('*/*/');
    }
}
