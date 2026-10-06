<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Adminhtml\Post;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\Blog\Api\Data\PostInterfaceFactory;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Model\Image\FormImage;
use Panth\Blog\Model\Post\LinkManager;

class Save extends Post implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Panth_Blog::post_save';

    public function __construct(
        Context $context,
        private readonly PostRepositoryInterface $repository,
        private readonly PostInterfaceFactory $factory,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly FormImage $formImage,
        private readonly LinkManager $linkManager
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

        $id = (int)($this->getRequest()->getParam('id') ?: ($data['post_id'] ?? 0));

        try {
            $model = $id
                ? $this->repository->getById($id)
                : $this->factory->create();

            $data = $this->formImage->applyToPostData($data, ['featured_image', 'og_image']);
            unset($data['post_id']);
            $links = array_intersect_key($data, array_flip([
                'store_ids', 'category_ids', 'primary_category_id', 'tag_ids', 'related_ids', LinkManager::MARKER,
            ]));
            $model->setData(array_merge($model->getData(), array_diff_key($data, $links)));
            if (!empty($links[LinkManager::MARKER])) {
                $model->setData('store_ids', $links['store_ids'] ?? []);
            }
            $saved = $this->repository->save($model);
            $this->linkManager->save((int) $saved->getPostId(), $links);

            $this->messageManager->addSuccessMessage(__('The post has been saved.'));
            $this->dataPersistor->clear('panth_blog_post');

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['id' => $saved->getPostId()]);
            }
            return $resultRedirect->setPath('*/*/');
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('This post no longer exists.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the post.'));
        }

        $this->dataPersistor->set('panth_blog_post', $data);
        return $resultRedirect->setPath('*/*/edit', $id ? ['id' => $id] : []);
    }
}
