<?php
declare(strict_types=1);

namespace Panth\Blog\Api\Data;

/** Search results container for Panth_Blog tags. */
interface TagSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface
{
    /**
     * Get the tags in this result page.
     *
     * @return \Panth\Blog\Api\Data\TagInterface[]
     */
    public function getItems(): array;

    /**
     * Set the tags in this result page.
     *
     * @param \Panth\Blog\Api\Data\TagInterface[] $items
     * @return $this
     */
    public function setItems(array $items): self;
}
