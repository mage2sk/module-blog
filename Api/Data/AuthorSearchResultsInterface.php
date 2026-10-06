<?php
declare(strict_types=1);

namespace Panth\Blog\Api\Data;

/** Search results container for Panth_Blog authors. */
interface AuthorSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface
{
    /**
     * Get the authors in this result page.
     *
     * @return \Panth\Blog\Api\Data\AuthorInterface[]
     */
    public function getItems(): array;

    /**
     * Set the authors in this result page.
     *
     * @param \Panth\Blog\Api\Data\AuthorInterface[] $items
     * @return $this
     */
    public function setItems(array $items): self;
}
