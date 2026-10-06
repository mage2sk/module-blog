<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Resolver;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Panth\Blog\Api\AuthorRepositoryInterface;

class AuthorsResolver implements ResolverInterface
{
    public function __construct(
        private readonly AuthorRepositoryInterface $authorRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): array
    {
        try {
            $this->searchCriteriaBuilder->addFilter('is_active', 1);
            $criteria = $this->searchCriteriaBuilder->create();
            $results = $this->authorRepository->getList($criteria);

            $out = [];
            foreach ($results->getItems() as $author) {
                if (method_exists($author, 'getData')) {
                    $out[] = $author->getData();
                }
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }
}
