<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model\Text;

use Panth\Blog\Model\Text\Truncator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TruncatorTest extends TestCase
{
    private Truncator $truncator;

    protected function setUp(): void
    {
        $this->truncator = new Truncator();
    }

    public static function multibyteSummaries(): array
    {
        return [
            'devanagari' => [str_repeat('पानी की बोतल स्टेनलेस स्टील ', 8)],
            'japanese' => [str_repeat('ステンレス製真空断熱ボトル ', 12)],
            'accented latin' => [str_repeat('Bouteille isotherme en acier inoxydable ', 5)],
        ];
    }

    #[DataProvider('multibyteSummaries')]
    public function testAMultibyteSummaryUnderTheLimitIsNeverCut(string $content): void
    {
        $max = mb_strlen($content, 'UTF-8') + 10;

        $this->assertSame($content, $this->truncator->truncate($content, $max));
    }

    #[DataProvider('multibyteSummaries')]
    public function testAMultibyteSummaryIsNeverCutIntoInvalidUtf8(string $content): void
    {
        for ($max = 20; $max <= 340; $max += 7) {
            $result = $this->truncator->truncate($content, $max);
            $this->assertTrue(
                mb_check_encoding($result, 'UTF-8'),
                'limit ' . $max . ' produced invalid UTF-8, which makes an Atom or RSS document unparseable'
            );
            $this->assertLessThanOrEqual(
                $max,
                mb_strlen($result, 'UTF-8'),
                'limit ' . $max . ' overshot'
            );
        }
    }

    public function testTheFeedLimitKeepsMultibyteContentThatFits(): void
    {
        $content = str_repeat('पानी की बोतल स्टेनलेस स्टील ', 8);
        $this->assertGreaterThan(320, strlen($content), 'fixture must exceed 320 bytes');
        $this->assertLessThan(320, mb_strlen($content, 'UTF-8'), 'fixture must be under 320 characters');

        $this->assertSame($content, $this->truncator->truncate($content, 320));
    }

    public function testLongLatinContentIsTruncatedWithinTheLimit(): void
    {
        $content = str_repeat('a', 500);
        $result = $this->truncator->truncate($content, 320);

        $this->assertSame(320, mb_strlen($result, 'UTF-8'));
        $this->assertStringEndsWith('...', $result);
    }

    public function testShortAndDegenerateInputsAreLeftAlone(): void
    {
        $this->assertSame('', $this->truncator->truncate('', 320));
        $this->assertSame('short', $this->truncator->truncate('short', 320));
        $this->assertSame('a long value', $this->truncator->truncate('a long value', 3));
    }
}
