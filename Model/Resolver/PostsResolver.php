<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Resolver;

use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Model\StoreVisibility;

class PostsResolver implements ResolverInterface
{
    public function __construct(
        private readonly PostRepositoryInterface $postRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly FilterGroupBuilder $filterGroupBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        private readonly StoreVisibility $storeVisibility,
        private readonly ResourceConnection $resource
    ) {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): array
    {
        $pageSize = max(1, min(100, (int) ($args['pageSize'] ?? 10)));
        $currentPage = max(1, (int) ($args['currentPage'] ?? 1));
        $empty = [
            'items'       => [],
            'total_count' => 0,
            'page_info'   => [
                'page_size'    => $pageSize,
                'current_page' => $currentPage,
                'total_pages'  => 0,
            ],
        ];
        try {
            $filter = is_array($args['filter'] ?? null) ? $args['filter'] : [];
            $ids = $this->resolveFilterIds($filter);
            if ($ids === []) {
                return $empty;
            }

            $this->searchCriteriaBuilder->setPageSize($pageSize)->setCurrentPage($currentPage);
            $this->searchCriteriaBuilder->addSortOrder(
                $this->sortOrderBuilder->setField('published_at')->setDirection(SortOrder::SORT_DESC)->create()
            );
            $this->searchCriteriaBuilder->addSortOrder(
                $this->sortOrderBuilder->setField('post_id')->setDirection(SortOrder::SORT_DESC)->create()
            );
            $this->searchCriteriaBuilder->addFilter('status', PostInterface::STATUS_PUBLISHED);
            $this->searchCriteriaBuilder->addFilter('store_id', (string) $this->storeVisibility->getCurrentStoreId());
            if ($ids !== null) {
                $this->searchCriteriaBuilder->addFilter('post_id', $ids, 'in');
            }

            $criteria = $this->searchCriteriaBuilder->create();
            $results = $this->postRepository->getList($criteria);

            $items = [];
            foreach ($results->getItems() as $post) {
                if (method_exists($post, 'getData')) {
                    $items[] = $post->getData();
                }
            }

            $total = (int) $results->getTotalCount();

            return [
                'items'       => $items,
                'total_count' => $total,
                'page_info'   => [
                    'page_size'    => $pageSize,
                    'current_page' => $currentPage,
                    'total_pages'  => (int) ceil($total / $pageSize),
                ],
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }

    private function resolveFilterIds(array $filter): ?array
    {
        $connection = $this->resource->getConnection();
        $postTable = $this->resource->getTableName('panth_blog_post');
        $select = $connection->select()->from(['p' => $postTable], ['post_id']);
        $filtered = false;

        $categoryKey = trim((string) ($filter['category_url_key'] ?? ''));
        if ($categoryKey !== '') {
            $select->join(
                ['pc' => $this->resource->getTableName('panth_blog_post_category')],
                'pc.post_id = p.post_id',
                []
            )->join(
                ['c' => $this->resource->getTableName('panth_blog_category')],
                'c.category_id = pc.category_id',
                []
            )->where('c.url_key = ?', $categoryKey)->where('c.is_active = ?', 1);
            $this->storeVisibility->filterCategories($select, 'c.category_id');
            $filtered = true;
        }

        $tagKey = trim((string) ($filter['tag_url_key'] ?? ''));
        if ($tagKey !== '') {
            $select->join(
                ['pt' => $this->resource->getTableName('panth_blog_post_tag')],
                'pt.post_id = p.post_id',
                []
            )->join(
                ['t' => $this->resource->getTableName('panth_blog_tag')],
                't.tag_id = pt.tag_id',
                []
            )->where('t.url_key = ?', $tagKey);
            $filtered = true;
        }

        $authorKey = trim((string) ($filter['author_url_key'] ?? ''));
        if ($authorKey !== '') {
            $select->join(
                ['a' => $this->resource->getTableName('panth_blog_author')],
                'a.author_id = p.author_id',
                []
            )->where('a.url_key = ?', $authorKey)->where('a.is_active = ?', 1);
            $filtered = true;
        }

        if (!$filtered) {
            return null;
        }
        return array_values(array_unique(array_map('intval', $connection->fetchCol($select->distinct(true)))));
    }
}
