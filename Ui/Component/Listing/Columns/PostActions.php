<?php
declare(strict_types=1);

namespace Panth\Blog\Ui\Component\Listing\Columns;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class PostActions extends Column
{
    private const URL_PATH_EDIT = 'panth_blog/post/edit';
    private const URL_PATH_DELETE = 'panth_blog/post/delete';
    private const URL_PATH_DUPLICATE = 'panth_blog/post/duplicate';

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item['post_id'])) {
                continue;
            }
            $id = (int)$item['post_id'];
            $item[$this->getData('name')] = [
                'edit' => [
                    'href' => $this->urlBuilder->getUrl(self::URL_PATH_EDIT, ['id' => $id]),
                    'label' => __('Edit'),
                ],
                'duplicate' => [
                    'href' => $this->urlBuilder->getUrl(self::URL_PATH_DUPLICATE, ['id' => $id]),
                    'label' => __('Duplicate'),
                    'post' => true,
                ],
                'delete' => [
                    'href' => $this->urlBuilder->getUrl(self::URL_PATH_DELETE, ['id' => $id]),
                    'label' => __('Delete'),
                    'post' => true,
                    'confirm' => [
                        'title' => __('Delete Post'),
                        'message' => __('Are you sure you want to delete this post?'),
                    ],
                ],
            ];
        }

        return $dataSource;
    }
}
