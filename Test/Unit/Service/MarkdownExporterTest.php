<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Service;

use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Service\MarkdownExporter;
use PHPUnit\Framework\TestCase;

class MarkdownExporterTest extends TestCase
{
    private function post(array $data): PostInterface
    {
        $post = $this->createStub(PostInterface::class);
        $post->method('getTitle')->willReturn($data['title'] ?? 'Title');
        $post->method('getUrlKey')->willReturn($data['url_key'] ?? 'title');
        $post->method('getPublishedAt')->willReturn($data['published_at'] ?? null);
        $post->method('getShortDescription')->willReturn($data['short'] ?? null);
        $post->method('getContent')->willReturn($data['content'] ?? null);
        return $post;
    }

    public function testFrontMatterIsJsonEncodedTitle(): void
    {
        $md = (new MarkdownExporter())->toMarkdown($this->post([
            'title' => 'Say "hi" / caf' . "\u{e9}",
            'url_key' => 'say-hi',
            'published_at' => '2026-01-02 03:04:05',
        ]));

        $expected = "---\ntitle: \"Say \\\"hi\\\" / caf\u{e9}\"\nurl_key: say-hi\npublished_at: 2026-01-02 03:04:05\n---\n\n";
        $this->assertStringStartsWith($expected, $md);
    }

    public function testEmptyContentProducesOnlyFrontMatter(): void
    {
        $md = (new MarkdownExporter())->toMarkdown($this->post([]));
        $this->assertSame("---\ntitle: \"Title\"\nurl_key: title\npublished_at: \n---\n\n", $md);
    }

    public function testShortDescriptionBecomesBlockquote(): void
    {
        $md = (new MarkdownExporter())->toMarkdown($this->post(['short' => "Line one\nLine two\n"]));
        $this->assertStringContainsString("> Line one\n> Line two\n\n", $md);
    }

    public function testHtmlIsConvertedToMarkdown(): void
    {
        $html = '<h2>Section</h2><p>Some <strong>bold</strong> and <em>italic</em> with '
            . '<a href="https://example.com/x">a link</a>.</p><ul><li>One</li><li>Two</li></ul>'
            . '<pre><code class="php">echo 1;</code></pre><p>A &amp; B<br>next</p>';

        $md = (new MarkdownExporter())->toMarkdown($this->post(['content' => $html]));

        $this->assertStringContainsString("## Section\n\n", $md);
        $this->assertStringContainsString('Some **bold** and *italic* with [a link](https://example.com/x).', $md);
        $this->assertStringContainsString("- One\n- Two\n", $md);
        $this->assertStringContainsString("```\necho 1;\n```", $md);
        $this->assertStringContainsString("A & B\nnext", $md);
        $this->assertStringNotContainsString("\n\n\n", $md);
        $this->assertStringEndsWith("next\n", $md);
    }
}
