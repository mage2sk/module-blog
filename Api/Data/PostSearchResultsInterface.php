<?php
declare(strict_types=1);

namespace Panth\Blog\Api\Data;

/** Search results container for Panth_Blog posts. */
interface PostSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface
{
    /**
     * Get the posts in this result page.
     *
     * @return \Panth\Blog\Api\Data\PostInterface[]
     */
    public function getItems(): array;

    /**
     * Set the posts in this result page.
     *
     * @param \Panth\Blog\Api\Data\PostInterface[] $items
     * @return $this
     */
    public function setItems(array $items): self;
}
