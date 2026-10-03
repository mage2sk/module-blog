<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Category;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Panth\Blog\Model\Image\FormImage;
use Panth\Blog\Model\ResourceModel\Category\CollectionFactory;

class DataProvider extends AbstractDataProvider
{
    private const IMAGE_FIELDS = ['image'];

    private array $loadedData = [];

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly FormImage $formImage,
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
            $this->loadedData[$item->getId()] = $this->formImage->toFormData($item->getData(), self::IMAGE_FIELDS);
        }

        $persisted = $this->dataPersistor->get('panth_blog_category');
        if (!empty($persisted)) {
            $current = (array)current($this->loadedData);
            $merged = array_merge($current, (array)$persisted);
            $key = $current['category_id'] ?? null;
            $this->loadedData[$key] = $merged;
            $this->dataPersistor->clear('panth_blog_category');
        }

        return $this->loadedData;
    }
}
