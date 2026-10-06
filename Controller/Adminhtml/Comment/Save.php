<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Adminhtml\Comment;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\Blog\Api\CommentRepositoryInterface;
use Panth\Blog\Api\Data\CommentInterfaceFactory;

class Save extends Comment implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly CommentRepositoryInterface $repository,
        private readonly CommentInterfaceFactory $factory,
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

        $id = (int)($this->getRequest()->getParam('id') ?: ($data['comment_id'] ?? 0));

        try {
            $model = $id
                ? $this->repository->getById($id)
                : $this->factory->create();

            unset($data['comment_id']);
            if ($id) {
                foreach (['post_id', 'author_name', 'author_email', 'author_website', 'created_at', 'user_agent'] as $readOnly) {
                    unset($data[$readOnly]);
                }
            }
            $model->setData(array_merge($model->getData(), $data));
            $saved = $this->repository->save($model);

            $this->messageManager->addSuccessMessage(__('The comment has been saved.'));
            $this->dataPersistor->clear('panth_blog_comment');

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['id' => $saved->getCommentId()]);
            }
            return $resultRedirect->setPath('*/*/');
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('This comment no longer exists.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the comment.'));
        }

        $this->dataPersistor->set('panth_blog_comment', $data);
        return $resultRedirect->setPath('*/*/edit', $id ? ['id' => $id] : []);
    }
}
