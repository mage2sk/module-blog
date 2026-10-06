<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Service;

use Panth\Blog\Service\InlineFaqExtractor;
use PHPUnit\Framework\TestCase;

class InlineFaqExtractorTest extends TestCase
{
    private InlineFaqExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new InlineFaqExtractor();
    }

    public function testReturnsEmptyWithoutFaqMarker(): void
    {
        $this->assertSame([], $this->extractor->extract(''));
        $this->assertSame([], $this->extractor->extract('<dl><dt>Q</dt><dd>A</dd></dl>'));
    }

    public function testExtractsPairsFromMultipleBlocks(): void
    {
        $html = '<p>intro</p><dl class="pb-faq big"><dt><b>What?</b></dt> <dd>This.</dd><dt>Why?</dt><dd><em>Because</em></dd></dl>'
            . '<dl class="other pb-faq"><dt>How?</dt><dd>Carefully</dd></dl>';

        $this->assertSame([
            ['question' => 'What?', 'answer' => 'This.'],
            ['question' => 'Why?', 'answer' => 'Because'],
            ['question' => 'How?', 'answer' => 'Carefully'],
        ], $this->extractor->extract($html));
    }

    public function testSkipsEmptyQuestionsOrAnswers(): void
    {
        $html = '<dl class="pb-faq"><dt> </dt><dd>orphan</dd><dt>Q</dt><dd><span></span></dd><dt>Keep</dt><dd>me</dd></dl>';
        $this->assertSame([['question' => 'Keep', 'answer' => 'me']], $this->extractor->extract($html));
    }

    public function testMarkerWithoutDlBlockReturnsEmpty(): void
    {
        $this->assertSame([], $this->extractor->extract('<div class="pb-faq"><dt>Q</dt><dd>A</dd></div>'));
    }

    public function testBlockWithoutPairsIsIgnored(): void
    {
        $this->assertSame([], $this->extractor->extract('<dl class="pb-faq"><dt>Lonely</dt></dl>'));
    }
}
