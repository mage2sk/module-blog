<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Model\StoreVisibility;

class PostResolver implements ResolverInterface
{
    public function __construct(
        private readonly PostRepositoryInterface $postRepository,
        private readonly StoreVisibility $storeVisibility
    ) {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): ?array
    {
        try {
            $id = (int) ($args['id'] ?? 0);
            $urlKey = (string) ($args['urlKey'] ?? '');

            if ($id > 0) {
                $post = $this->postRepository->getById($id);
            } elseif ($urlKey !== '') {
                $post = $this->postRepository->getByUrlKey($urlKey);
            } else {
                return null;
            }

            if ($post->getStatus() !== PostInterface::STATUS_PUBLISHED
                || !$this->storeVisibility->isPostVisible((int) $post->getPostId())
            ) {
                return null;
            }

            return method_exists($post, 'getData') ? $post->getData() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
