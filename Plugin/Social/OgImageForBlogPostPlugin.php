<?php
declare(strict_types=1);

namespace Panth\Blog\Plugin\Social;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Helper\Config;
use Psr\Log\LoggerInterface;

class OgImageForBlogPostPlugin
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly PostRepositoryInterface $postRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function afterResolve($subject, array $tags): array
    {
        if (!$this->config->isOgUseFeaturedImage() || !$this->isBlogPostView()) {
            return $tags;
        }

        try {
            $slug = trim((string) $this->request->getParam('slug', ''));
            if ($slug === '') {
                return $tags;
            }

            $post = $this->postRepository->getByUrlKey($slug);
            $featured = (string) ($post->getOgImage() ?: $post->getFeaturedImage());
            if ($featured === '') {
                return $tags;
            }

            if (stripos($featured, 'http://') === 0 || stripos($featured, 'https://') === 0) {
                $tags['og:image'] = $featured;
            } else {
                $base = rtrim((string) $this->storeManager->getStore()
                    ->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/');
                $tags['og:image'] = $base . '/blog/' . ltrim($featured, '/');
            }
            $tags['og:image:secure_url'] = $tags['og:image'];
            $tags['og:image:width'] = '1200';
            $tags['og:image:height'] = '630';
            $tags['og:image:type'] = 'image/png';

            $alt = (string) ($post->getFeaturedImageAlt() ?: $post->getTitle());
            if ($alt !== '') {
                $tags['og:image:alt'] = $alt;
            }
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] OG image override failed: ' . $e->getMessage());
        }

        return $tags;
    }

    private function isBlogPostView(): bool
    {
        return $this->request->getModuleName() === 'blog'
            && $this->request->getControllerName() === 'post'
            && $this->request->getActionName() === 'view';
    }
}
