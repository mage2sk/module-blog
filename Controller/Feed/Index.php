<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Feed;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Feed\FeedRepository;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly ResultFactory $resultFactory,
        private readonly Config $config,
        private readonly FeedRepository $feedRepository,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->config->isEnabled() || !$this->config->isFeedEnabled()) {
            $raw = $this->resultFactory->create(ResultFactory::TYPE_RAW);
            $raw->setHttpResponseCode(404);
            $raw->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
            $raw->setContents('');
            return $raw;
        }

        $storeId = (int) $this->storeManager->getStore()->getId();
        $xml = $this->feedRepository->buildSiteWideRss($storeId);

        $raw = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $raw->setHeader('Content-Type', 'application/rss+xml; charset=utf-8', true);
        $raw->setContents($xml);
        return $raw;
    }
}
