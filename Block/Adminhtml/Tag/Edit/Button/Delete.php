<?php
declare(strict_types=1);

namespace Panth\Blog\Block\Adminhtml\Tag\Edit\Button;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class Delete extends GenericButton implements ButtonProviderInterface
{
    public function getButtonData(): array
    {
        $id = $this->getEntityId();
        if (!$id) {
            return [];
        }

        return [
            'label' => __('Delete'),
            'class' => 'delete',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s', {\"data\": {}})",
                __('Are you sure you want to delete this tag?'),
                $this->getUrl('*/*/delete', ['id' => $id])
            ),
            'sort_order' => 20,
        ];
    }
}
