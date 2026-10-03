<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Adminhtml\Post;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Model\ResourceModel\Post\CollectionFactory;

class MassStatus extends Post implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Panth_Blog::post_save';

    private const ALLOWED_STATUSES = [
        PostInterface::STATUS_DRAFT,
        PostInterface::STATUS_SCHEDULED,
        PostInterface::STATUS_PUBLISHED,
        PostInterface::STATUS_ARCHIVED,
    ];

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly PostRepositoryInterface $repository
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
                $post = $this->repository->getById((int)$id);
                $post->setStatus($status);
                $this->repository->save($post);
                $count++;
            } catch (\Throwable $e) {
                $this->messageManager->addErrorMessage(__('Post %1: %2', $id, $e->getMessage()));
            }
        }

        if ($count > 0) {
            $this->messageManager->addSuccessMessage(
                __('A total of %1 post(s) have been updated to %2.', $count, $status)
            );
        }

        return $resultRedirect->setPath('*/*/');
    }
}
