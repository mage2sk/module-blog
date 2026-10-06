<?php
declare(strict_types=1);

namespace Panth\Blog\Api\Data;

/** Search results container for Panth_Blog categories. */
interface CategorySearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface
{
    /**
     * Get the categories in this result page.
     *
     * @return \Panth\Blog\Api\Data\CategoryInterface[]
     */
    public function getItems(): array;

    /**
     * Set the categories in this result page.
     *
     * @param \Panth\Blog\Api\Data\CategoryInterface[] $items
     * @return $this
     */
    public function setItems(array $items): self;
}
