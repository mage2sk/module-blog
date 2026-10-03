<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Adminhtml\Tag;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\Blog\Api\Data\TagInterfaceFactory;
use Panth\Blog\Api\TagRepositoryInterface;

class Save extends Tag implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Panth_Blog::tag_save';

    public function __construct(
        Context $context,
        private readonly TagRepositoryInterface $repository,
        private readonly TagInterfaceFactory $factory,
        private readonly DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if (!$data) {
            return $resultRedirect->setPath('*/*/');
        }

        $id = (int)($this->getRequest()->getParam('id') ?: ($data['tag_id'] ?? 0));

        try {
            $model = $id
                ? $this->repository->getById($id)
                : $this->factory->create();

            unset($data['tag_id']);
            $model->setData(array_merge($model->getData(), $data));
            $saved = $this->repository->save($model);

            $this->messageManager->addSuccessMessage(__('The tag has been saved.'));
            $this->dataPersistor->clear('panth_blog_tag');

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['id' => $saved->getTagId()]);
            }
            return $resultRedirect->setPath('*/*/');
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('This tag no longer exists.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the tag.'));
        }

        $this->dataPersistor->set('panth_blog_tag', $data);
        return $resultRedirect->setPath('*/*/edit', $id ? ['id' => $id] : []);
    }
}
