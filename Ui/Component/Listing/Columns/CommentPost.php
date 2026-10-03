<?php
declare(strict_types=1);

namespace Panth\Blog\Ui\Component\Listing\Columns;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class CommentPost extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly ResourceConnection $resource,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (empty($dataSource['data']['items'])) {
            return $dataSource;
        }
        $name = $this->getData('name');
        $ids = [];
        foreach ($dataSource['data']['items'] as $item) {
            if (!empty($item[$name])) {
                $ids[(int) $item[$name]] = true;
            }
        }
        $titles = [];
        if ($ids) {
            $connection = $this->resource->getConnection();
            $select = $connection->select()
                ->from($this->resource->getTableName('panth_blog_post'), ['post_id', 'title'])
                ->where('post_id IN (?)', array_keys($ids));
            $titles = $connection->fetchPairs($select);
        }
        foreach ($dataSource['data']['items'] as &$item) {
            $postId = (int) ($item[$name] ?? 0);
            if ($postId && isset($titles[$postId])) {
                $item[$name] = $titles[$postId] . ' (#' . $postId . ')';
            }
        }
        unset($item);
        return $dataSource;
    }
}
