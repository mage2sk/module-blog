<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Adminhtml\Post;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Model\ResourceModel\Post\CollectionFactory;

class MassDelete extends Post implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Panth_Blog::post_delete';

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
                __('A total of %1 post(s) have been deleted.', $count)
            );
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
