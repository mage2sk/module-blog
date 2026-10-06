<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Comment;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Panth\Blog\Model\ResourceModel\Comment\CollectionFactory;

class DataProvider extends AbstractDataProvider
{
    private array $loadedData = [];

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    public function getData(): array
    {
        if (!empty($this->loadedData)) {
            return $this->loadedData;
        }

        $items = $this->collection->getItems();
        foreach ($items as $item) {
            $this->loadedData[$item->getId()] = $item->getData();
        }

        $persisted = $this->dataPersistor->get('panth_blog_comment');
        if (!empty($persisted)) {
            $current = (array)current($this->loadedData);
            $merged = array_merge($current, (array)$persisted);
            $key = $current['comment_id'] ?? null;
            $this->loadedData[$key] = $merged;
            $this->dataPersistor->clear('panth_blog_comment');
        }

        return $this->loadedData;
    }
}
