<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Post;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Panth\Blog\Model\Image\FormImage;
use Panth\Blog\Model\ResourceModel\Post\CollectionFactory;

class DataProvider extends AbstractDataProvider
{
    private const IMAGE_FIELDS = ['featured_image', 'og_image'];

    private array $loadedData = [];

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly FormImage $formImage,
        private readonly LinkManager $linkManager,
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
            $this->loadedData[$item->getId()] = array_merge(
                $this->formImage->toFormData($item->getData(), self::IMAGE_FIELDS),
                $this->linkManager->getFormData((int) $item->getId())
            );
        }

        $persisted = $this->dataPersistor->get('panth_blog_post');
        if (!empty($persisted)) {
            $current = (array)current($this->loadedData);
            $merged = array_merge($current, (array)$persisted);
            $key = $current['post_id'] ?? null;
            $this->loadedData[$key] = $merged;
            $this->dataPersistor->clear('panth_blog_post');
        }

        return $this->loadedData;
    }
}
