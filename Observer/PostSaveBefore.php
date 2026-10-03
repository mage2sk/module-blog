<?php
declare(strict_types=1);

namespace Panth\Blog\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Reader\ReadingTimeCalculator;
use Panth\Blog\Model\Url\SlugGenerator;
use Psr\Log\LoggerInterface;

class PostSaveBefore implements ObserverInterface
{
    private const TLDR_MIN_CHARS = 200;

    private const TLDR_MIN_WORDS = 80;
    private const TLDR_MAX_WORDS = 120;

    private const READING_TIME_CLASS = 'Panth\\Blog\\Model\\Reader\\ReadingTimeCalculator';

    public function __construct(
        private readonly SlugGenerator $slugGenerator,
        private readonly PostRepositoryInterface $postRepository,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly ?ReadingTimeCalculator $readingTimeCalculator = null
    ) {
    }

    public function execute(Observer $observer): void
    {
        try {
            $post = $observer->getEvent()->getData('post');
            if (!$post instanceof PostInterface) {
                return;
            }

            $this->ensureUrlKey($post);
            $this->computeReadingMetrics($post);
            $this->autoExtractTldr($post);
        } catch (\Throwable $e) {
            $this->logger->warning('[PanthBlog PostSaveBefore] ' . $e->getMessage());
        }
    }

    private function ensureUrlKey(PostInterface $post): void
    {
        $current = (string) $post->getUrlKey();
        if ($current !== '') {
            return;
        }

        $title = (string) $post->getTitle();
        if ($title === '') {
            return;
        }

        $base = $this->slugGenerator->generate($title);
        if ($base === '') {
            return;
        }

        $candidate = $base;
        $n = 2;

        while ($n < 100 && $this->urlKeyExists($candidate, (int) ($post->getPostId() ?? 0))) {
            $candidate = $this->slugGenerator->withSuffix($base, $n);
            $n++;
        }

        $post->setUrlKey($candidate);
    }

    private function urlKeyExists(string $urlKey, int $ignorePostId): bool
    {
        try {
            $existing = $this->postRepository->getByUrlKey($urlKey);
            return ((int) $existing->getPostId()) !== $ignorePostId;
        } catch (\Throwable) {
            return false;
        }
    }

    private function computeReadingMetrics(PostInterface $post): void
    {
        if ($this->readingTimeCalculator === null) {
            return;
        }
        if (!class_exists(self::READING_TIME_CLASS)) {
            return;
        }
        if (!method_exists($this->readingTimeCalculator, 'calculate')) {
            return;
        }

        $content = (string) $post->getContent();
        if ($content === '') {
            return;
        }

        $wpm = $this->config->getReadingSpeedWpm();
        $result = $this->readingTimeCalculator->calculate($content, $wpm);
        if (!is_array($result)) {
            return;
        }

        if (isset($result['word_count'])) {
            $post->setWordCount((int) $result['word_count']);
        }
        if (isset($result['reading_time_min'])) {
            $post->setReadingTimeMin((int) $result['reading_time_min']);
        }
    }

    private function autoExtractTldr(PostInterface $post): void
    {
        if (!$this->config->isAutoExtractTldr()) {
            return;
        }
        if ((string) $post->getTldrSummary() !== '') {
            return;
        }

        $content = (string) $post->getContent();
        if ($content === '') {
            return;
        }

        $content = preg_replace('#<h[1-6]\b[^>]*>.*?</h[1-6]>#isu', ' ', $content) ?? $content;
        $content = preg_replace('#<(/?)(p|div|br|li|ul|ol|blockquote|section|article|tr|td|th|figcaption|dd|dt)\b([^>]*)>#iu', ' <$1$2$3> ', $content) ?? $content;
        $plain = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        if (mb_strlen($plain) <= self::TLDR_MIN_CHARS) {
            return;
        }

        $words = preg_split('/\s+/u', $plain) ?: [];
        if (count($words) < self::TLDR_MIN_WORDS) {
            return;
        }

        $take = min(self::TLDR_MAX_WORDS, count($words));
        $summary = implode(' ', array_slice($words, 0, $take));

        $lastStop = max(
            (int) strrpos($summary, '.'),
            (int) strrpos($summary, '!'),
            (int) strrpos($summary, '?')
        );
        if ($lastStop > 0 && $lastStop > (int) (mb_strlen($summary) * 0.5)) {
            $summary = substr($summary, 0, $lastStop + 1);
        }

        $post->setTldrSummary($summary);
    }
}
