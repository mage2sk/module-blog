<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Feed;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Api\CategoryRepositoryInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Feed\FeedRepository;
use Panth\Blog\Model\StoreVisibility;

class Category implements HttpGetActionInterface
{
    public function __construct(
        private readonly ResultFactory $resultFactory,
        private readonly RequestInterface $request,
        private readonly Config $config,
        private readonly FeedRepository $feedRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly StoreVisibility $storeVisibility
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->config->isEnabled()
            || !$this->config->isFeedEnabled()
            || !$this->config->isPerCategoryFeeds()) {
            return $this->notFound();
        }

        $slug = trim((string) $this->request->getParam('slug', ''));
        if ($slug === '') {
            return $this->notFound();
        }

        $storeId = (int) $this->storeManager->getStore()->getId();

        try {
            $category = $this->categoryRepository->getByUrlKey($slug, $storeId);
        } catch (NoSuchEntityException) {
            return $this->notFound();
        }

        if (!$category->getIsActive()
            || $category->getCategoryId() === null
            || !$this->storeVisibility->isCategoryVisible((int) $category->getCategoryId(), $storeId)
        ) {
            return $this->notFound();
        }

        $xml = $this->feedRepository->buildCategoryRss((int) $category->getCategoryId(), $storeId);

        $raw = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $raw->setHeader('Content-Type', 'application/rss+xml; charset=utf-8', true);
        $raw->setContents($xml);
        return $raw;
    }

    private function notFound(): ResultInterface
    {
        $raw = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $raw->setHttpResponseCode(404);
        $raw->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
        $raw->setContents('');
        return $raw;
    }
}
