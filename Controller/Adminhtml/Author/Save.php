<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Adminhtml\Author;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\Blog\Api\AuthorRepositoryInterface;
use Panth\Blog\Api\Data\AuthorInterfaceFactory;
use Panth\Blog\Model\Image\FormImage;

class Save extends Author implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Panth_Blog::author_save';

    public function __construct(
        Context $context,
        private readonly AuthorRepositoryInterface $repository,
        private readonly AuthorInterfaceFactory $factory,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly FormImage $formImage
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

        $id = (int)($this->getRequest()->getParam('id') ?: ($data['author_id'] ?? 0));

        try {
            $model = $id
                ? $this->repository->getById($id)
                : $this->factory->create();

            $data = $this->formImage->applyToPostData($data, ['avatar']);
            unset($data['author_id']);
            $model->setData(array_merge($model->getData(), $data));
            $saved = $this->repository->save($model);

            $this->messageManager->addSuccessMessage(__('The author has been saved.'));
            $this->dataPersistor->clear('panth_blog_author');

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['id' => $saved->getAuthorId()]);
            }
            return $resultRedirect->setPath('*/*/');
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('This author no longer exists.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the author.'));
        }

        $this->dataPersistor->set('panth_blog_author', $data);
        return $resultRedirect->setPath('*/*/edit', $id ? ['id' => $id] : []);
    }
}
