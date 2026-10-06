<?php
declare(strict_types=1);

namespace Panth\Blog\Service;

use Panth\Blog\Api\Data\PostInterface;

class MarkdownExporter
{
    public function __construct()
    {
    }

    public function toMarkdown(PostInterface $post): string
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
}
