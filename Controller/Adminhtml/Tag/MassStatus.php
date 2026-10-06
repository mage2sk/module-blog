<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Adminhtml\Tag;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use Panth\Blog\Api\TagRepositoryInterface;
use Panth\Blog\Model\ResourceModel\Tag\CollectionFactory;

class MassStatus extends Tag implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Panth_Blog::tag_save';

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly TagRepositoryInterface $repository
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $type = (string)$this->getRequest()->getParam('type', '');

        if (!in_array($type, ['index', 'noindex'], true)) {
            $this->messageManager->addErrorMessage(__('Invalid robots action.'));
            return $resultRedirect->setPath('*/*/');
        }
        $robots = $type === 'index' ? 'index,follow' : 'noindex,follow';

        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $count = 0;
        foreach ($collection->getAllIds() as $id) {
            try {
                $tag = $this->repository->getById((int)$id);
                $tag->setMetaRobots($robots);
                $this->repository->save($tag);
                $count++;
            } catch (\Throwable $e) {
                $this->messageManager->addErrorMessage(__('Tag %1: %2', $id, $e->getMessage()));
            }
        }

        if ($count > 0) {
            $this->messageManager->addSuccessMessage(
                __('A total of %1 tag(s) updated to %2.', $count, $robots)
            );
        }

        return $resultRedirect->setPath('*/*/');
    }
}
