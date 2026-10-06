<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\View;

use PHPUnit\Framework\TestCase;

class BlogReadingStylesheetTest extends TestCase
{
    private const STYLESHEET = 'view/frontend/web/css/blog.css';

    private string $css = '';

    protected function setUp(): void
    {
        $path = dirname(__DIR__, 3) . '/' . self::STYLESHEET;
        $this->assertTrue(is_file($path), self::STYLESHEET . ' is missing');
        $this->css = (string) file_get_contents($path);
    }

    private function lastRuleBody(string $selector): string
    {
        $start = strrpos($this->css, "\n" . $selector . ' {');
        $this->assertNotFalse($start, 'Selector not found: ' . $selector);
        $open = strpos($this->css, '{', (int) $start);
        $close = strpos($this->css, '}', (int) $open);

        return substr($this->css, (int) $open + 1, (int) $close - (int) $open - 1);
    }

    private function readingBlock(): string
    {
        $start = strrpos($this->css, "\n.pb-prose {\n    line-height: 1.5;");
        $this->assertNotFalse($start, 'Reading block not found');

        return substr($this->css, (int) $start);
    }

    public function testPostBodyTypeScale(): void
    {
        $this->assertStringContainsString('line-height: 1.5;', $this->lastRuleBody('.pb-prose'));
        $this->assertStringContainsString('max-width: 72ch;', $this->lastRuleBody('.pb-prose :is(p, li)'));
        $this->assertStringContainsString('font-size: 22px;', $this->lastRuleBody('.pb-prose h3'));
        $this->assertStringContainsString('max-width: 100%;', $this->lastRuleBody('.pb-prose img'));
        $this->assertStringContainsString('font-size: 16px;', $this->lastRuleBody('.pb-tldr__text'));
    }

    public function testDesktopHeadingsAreTwentyEightAndThirtySix(): void
    {
        $block = $this->readingBlock();

        $this->assertStringContainsString(".pb-prose h2 {\n    font-size: 28px;", $block);
        $this->assertStringContainsString(
            ".pb-page .pb-post__title,\n.pb-page .pb-page-header h1,\n.pb-page .pb-banner__title {\n"
            . "    font-size: 36px;",
            $block
        );
        $this->assertStringContainsString(
            ".pb-page .pb-featured-post__title,\n.pb-page .pb-related__title {\n    font-size: 28px;",
            $block
        );
    }

    public function testPhoneHeadingsAreTwentyEightAndTwentyFour(): void
    {
        $block = $this->readingBlock();

        $this->assertStringContainsString("    .pb-prose h2 {\n        font-size: 24px;", $block);
        $this->assertStringContainsString(
            "    .pb-page .pb-post__title,\n    .pb-page .pb-page-header h1,\n    .pb-page .pb-banner__title {\n"
            . "        font-size: 28px;",
            $block
        );
    }

    public function testSectionsAreSixtyFourApartAndFortyOnPhones(): void
    {
        $block = $this->readingBlock();

        $this->assertStringContainsString(".pb-page .pb-related,\n.pb-comments {\n    margin-top: 64px;", $block);
        $this->assertStringContainsString(
            "    .pb-page .pb-related,\n    .pb-comments {\n        margin-top: 40px;",
            $block
        );
    }

    public function testSidebarOnlyFromTenTwentyFour(): void
    {
        $this->assertStringContainsString(
            "@media (min-width: 1024px) {\n    .pb-layout {\n        grid-template-columns: 1fr 320px;",
            $this->css
        );
    }

    public function testStylesheetStaysValid(): void
    {
        $this->assertSame(substr_count($this->css, '{'), substr_count($this->css, '}'));
        $this->assertSame(1, preg_match('/^[\x09\x0A\x20-\x7E]*$/', $this->css));
    }
}
