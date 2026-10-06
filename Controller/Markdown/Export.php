<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Markdown;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\StoreVisibility;

class Export implements HttpGetActionInterface
{
    public function __construct(
        private readonly ResultFactory $resultFactory,
        private readonly RequestInterface $request,
        private readonly Config $config,
        private readonly PostRepositoryInterface $postRepository,
        private readonly StoreVisibility $storeVisibility
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->config->isEnabled() || !$this->config->isMarkdownExportEnabled()) {
            return $this->notFound();
        }

        $slug = trim((string) $this->request->getParam('slug', ''));
        if ($slug === '') {
            return $this->notFound();
        }

        try {
            $post = $this->postRepository->getByUrlKey($slug);
        } catch (NoSuchEntityException) {
            return $this->notFound();
        }

        if ($post->getStatus() !== PostInterface::STATUS_PUBLISHED
            || !$this->storeVisibility->isPostVisible((int) $post->getPostId())
        ) {
            return $this->notFound();
        }

        $body = $this->toMarkdown($post);

        $raw = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $raw->setHeader('Content-Type', 'text/markdown; charset=utf-8', true);
        $raw->setContents($body);
        return $raw;
    }

    private function toMarkdown(PostInterface $post): string
    {
        $out = "---\n";
        $out .= 'title: ' . json_encode($post->getTitle(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        $out .= 'url_key: ' . $post->getUrlKey() . "\n";
        $publishedAt = (string) ($post->getPublishedAt() ?? '');
        $out .= 'published_at: ' . $publishedAt . "\n";
        $out .= "---\n\n";

        $shortDescription = (string) ($post->getShortDescription() ?? '');
        if ($shortDescription !== '') {
            $out .= '> ' . str_replace("\n", "\n> ", trim($shortDescription)) . "\n\n";
        }

        $out .= $this->htmlToMarkdown((string) ($post->getContent() ?? ''));
        return $out;
    }

    private function htmlToMarkdown(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $patterns = [
            '#<pre><code[^>]*>(.*?)</code></pre>#is' => "\n```\n$1\n```\n\n",
            '#<h1[^>]*>(.*?)</h1>#is' => "# $1\n\n",
            '#<h2[^>]*>(.*?)</h2>#is' => "## $1\n\n",
            '#<h3[^>]*>(.*?)</h3>#is' => "### $1\n\n",
            '#<h4[^>]*>(.*?)</h4>#is' => "#### $1\n\n",
            '#<h5[^>]*>(.*?)</h5>#is' => "##### $1\n\n",
            '#<h6[^>]*>(.*?)</h6>#is' => "###### $1\n\n",
            '#<strong[^>]*>(.*?)</strong>#is' => '**$1**',
            '#<b[^>]*>(.*?)</b>#is' => '**$1**',
            '#<em[^>]*>(.*?)</em>#is' => '*$1*',
            '#<i[^>]*>(.*?)</i>#is' => '*$1*',
            '#<a\s+[^>]*href="([^"]+)"[^>]*>(.*?)</a>#is' => '[$2]($1)',
            '#<li[^>]*>(.*?)</li>#is' => "- $1\n",
            '#<br\s*/?>#i' => "\n",
            '#<p[^>]*>(.*?)</p>#is' => "$1\n\n",
        ];

        $out = preg_replace(array_keys($patterns), array_values($patterns), $html) ?? $html;
        $out = strip_tags($out);
        $out = html_entity_decode($out, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $out = preg_replace("/\n{3,}/", "\n\n", $out) ?? $out;
        return trim($out) . "\n";
    }

    private function notFound(): ResultInterface
    {
        $raw = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $raw->setHttpResponseCode(404);
        $raw->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
        $raw->setHeader('Cache-Control', 'no-store, max-age=0', true);
        $raw->setContents("Not Found\n");
        return $raw;
    }
}
