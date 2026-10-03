<?php
declare(strict_types=1);

namespace Panth\Blog\Block\Adminhtml\Tag\Edit\Button;

use Magento\Backend\Block\Widget\Context;

abstract class GenericButton
{
    public function __construct(protected Context $context)
    {
    }

    public function getEntityId(): ?int
    {
        $id = $this->context->getRequest()->getParam('id');
        return $id ? (int)$id : null;
    }

    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
