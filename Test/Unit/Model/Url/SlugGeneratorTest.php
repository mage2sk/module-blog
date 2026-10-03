<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model\Url;

use Panth\Blog\Model\Url\SlugGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SlugGeneratorTest extends TestCase
{
    private SlugGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new SlugGenerator();
    }

    public static function slugProvider(): array
    {
        return [
            'basic' => ['Hello World', 'hello-world'],
            'stop words removed' => ['The Art of War and Peace', 'art-war-peace'],
            'apostrophes dropped' => ["Don't Stop Me", 'dont-stop-me'],
            'curly apostrophe' => ["Kishan\u{2019}s Blog", 'kishans-blog'],
            'html stripped' => ['<b>Bold</b> <i>move</i>', 'bold-move'],
            'punctuation collapsed' => ['  Foo!!!  Bar???  ', 'foo-bar'],
            'only stop words kept' => ['The And Of', 'the-and-of'],
            'empty' => ['', ''],
            'only symbols' => ['!!!', ''],
            'numbers' => ['Top 10 Tips 2026', 'top-10-tips-2026'],
        ];
    }

    #[DataProvider('slugProvider')]
    public function testGenerate(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->generator->generate($input));
    }

    public function testKeepStopWords(): void
    {
        $this->assertSame('the-art-of-war', $this->generator->generate('The Art of War', true));
    }

    public function testLongInputIsCutAtWordBoundary(): void
    {
        $slug = $this->generator->generate(str_repeat('alpha beta ', 20));

        $this->assertLessThanOrEqual(80, strlen($slug));
        $this->assertTrue($this->generator->isValid($slug));
        $this->assertMatchesRegularExpression('/(alpha|beta)$/', $slug);
    }

    public function testLongSingleWordIsHardCut(): void
    {
        $this->assertSame(str_repeat('a', 80), $this->generator->generate(str_repeat('a', 120)));
    }

    public static function validityProvider(): array
    {
        return [
            ['hello-world', true],
            ['a', true],
            ['abc123', true],
            ['', false],
            ['Hello', false],
            ['-lead', false],
            ['trail-', false],
            ['double--hyphen', false],
            ['under_score', false],
            [str_repeat('a', 80), true],
            [str_repeat('a', 81), false],
        ];
    }

    #[DataProvider('validityProvider')]
    public function testIsValid(string $slug, bool $expected): void
    {
        $this->assertSame($expected, $this->generator->isValid($slug));
    }

    public function testWithSuffixAppendsWhenShort(): void
    {
        $this->assertSame('post-2', $this->generator->withSuffix('post', 2));
    }

    public function testWithSuffixTruncatesLongBaseAtHyphen(): void
    {
        $base = str_repeat('word-', 16) . 'end';
        $result = $this->generator->withSuffix($base, 12);

        $this->assertLessThanOrEqual(80, strlen($result));
        $this->assertStringEndsWith('word-12', $result);
        $this->assertTrue($this->generator->isValid($result));
    }

    public function testWithSuffixHardCutsBaseWithoutHyphen(): void
    {
        $this->assertSame(str_repeat('x', 78) . '-3', $this->generator->withSuffix(str_repeat('x', 100), 3));
    }
}
