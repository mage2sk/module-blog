<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Adminhtml\Category;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\Blog\Api\CategoryRepositoryInterface;
use Panth\Blog\Api\Data\CategoryInterfaceFactory;
use Panth\Blog\Model\Category\StoreLinkManager;
use Panth\Blog\Model\Image\FormImage;

class Save extends Category implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Panth_Blog::category_save';

    public function __construct(
        Context $context,
        private readonly CategoryRepositoryInterface $repository,
        private readonly CategoryInterfaceFactory $factory,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly FormImage $formImage,
        private readonly StoreLinkManager $storeLinkManager
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

        $id = (int)($this->getRequest()->getParam('id') ?: ($data['category_id'] ?? 0));

        try {
            $model = $id
                ? $this->repository->getById($id)
                : $this->factory->create();

            $data = $this->formImage->applyToPostData($data, ['image']);
            unset($data['category_id']);
            foreach (['parent_id', 'posts_per_page'] as $nullable) {
                if (array_key_exists($nullable, $data) && trim((string) $data[$nullable]) === '') {
                    $data[$nullable] = null;
                }
            }
            if ($id && isset($data['parent_id']) && (int) $data['parent_id'] === $id) {
                $data['parent_id'] = null;
            }
            $links = array_intersect_key($data, array_flip(['store_ids', StoreLinkManager::MARKER]));
            $model->setData(array_merge($model->getData(), array_diff_key($data, $links)));
            $saved = $this->repository->save($model);
            $this->storeLinkManager->save((int) $saved->getCategoryId(), $links);

            $this->messageManager->addSuccessMessage(__('The category has been saved.'));
            $this->dataPersistor->clear('panth_blog_category');

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['id' => $saved->getCategoryId()]);
            }
            return $resultRedirect->setPath('*/*/');
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('This category no longer exists.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the category.'));
        }

        $this->dataPersistor->set('panth_blog_category', $data);
        return $resultRedirect->setPath('*/*/edit', $id ? ['id' => $id] : []);
    }
}
