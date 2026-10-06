<?php
declare(strict_types=1);

namespace Panth\Blog\Api\Data;

/** Search results container for Panth_Blog comments. */
interface CommentSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface
{
    /**
     * Get the comments in this result page.
     *
     * @return \Panth\Blog\Api\Data\CommentInterface[]
     */
    public function getItems(): array;

    /**
     * Set the comments in this result page.
     *
     * @param \Panth\Blog\Api\Data\CommentInterface[] $items
     * @return $this
     */
    public function setItems(array $items): self;
}
