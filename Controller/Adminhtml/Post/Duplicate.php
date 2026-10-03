<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Adminhtml\Post;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Api\Data\PostInterfaceFactory;
use Panth\Blog\Api\PostRepositoryInterface;

class Duplicate extends Post implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Panth_Blog::post_save';

    public function __construct(
        Context $context,
        private readonly PostRepositoryInterface $repository,
        private readonly PostInterfaceFactory $factory
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = (int)$this->getRequest()->getParam('id');

        if (!$id) {
            $this->messageManager->addErrorMessage(__('Missing post ID.'));
            return $resultRedirect->setPath('*/*/');
        }

        try {
            $source = $this->repository->getById($id);
            $clone = $this->factory->create();
            $data = $source->getData();
            unset($data['post_id'], $data['created_at'], $data['updated_at']);

            $clone->setData($data);
            $clone->setUrlKey(($source->getUrlKey() ?? '') . '-copy');
            $clone->setStatus(PostInterface::STATUS_DRAFT);
            $clone->setPublishedAt(null);
            $clone->setViewCount(0);
            $clone->setLikeCount(0);

            $saved = $this->repository->save($clone);
            $this->messageManager->addSuccessMessage(__('The post has been duplicated.'));
            return $resultRedirect->setPath('*/*/edit', ['id' => $saved->getPostId()]);
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $resultRedirect->setPath('*/*/');
        }
    }
}
