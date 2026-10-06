<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Resolver;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Panth\Blog\Api\TagRepositoryInterface;

class TagsResolver implements ResolverInterface
{
    public function __construct(
        private readonly TagRepositoryInterface $tagRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): array
    {
        try {
            $criteria = $this->searchCriteriaBuilder->create();
            $results = $this->tagRepository->getList($criteria);

            $out = [];
            foreach ($results->getItems() as $tag) {
                if (method_exists($tag, 'getData')) {
                    $out[] = $tag->getData();
                }
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }
}
