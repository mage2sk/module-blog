<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model\Reader;

use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Reader\ReadingTimeCalculator;
use PHPUnit\Framework\TestCase;

class ReadingTimeCalculatorTest extends TestCase
{
    public function testCountsWordsIgnoringMarkupAndEntities(): void
    {
        $result = (new ReadingTimeCalculator())->calculate('<p>Hello&nbsp;<strong>big</strong> world &amp; more</p>');
        $this->assertSame(5, $result['word_count']);
        $this->assertSame(1, $result['reading_time_min']);
    }

    public function testEmptyContentStillReportsOneMinute(): void
    {
        $this->assertSame(['word_count' => 0, 'reading_time_min' => 1], (new ReadingTimeCalculator())->calculate(''));
    }

    public function testRoundsUpMinutes(): void
    {
        $html = str_repeat('word ', 441);
        $this->assertSame(3, (new ReadingTimeCalculator())->calculate($html)['reading_time_min']);
        $this->assertSame(5, (new ReadingTimeCalculator())->calculate($html, 100)['reading_time_min']);
    }

    public function testNonPositiveWpmUsesConfiguredSpeed(): void
    {
        $config = $this->createMock(Config::class);
        $config->expects($this->once())->method('getReadingSpeedWpm')->willReturn(100);

        $result = (new ReadingTimeCalculator($config))->calculate(str_repeat('w ', 250), 0);
        $this->assertSame(3, $result['reading_time_min']);
    }

    public function testNonPositiveWpmWithoutConfigUsesDefault(): void
    {
        $result = (new ReadingTimeCalculator())->calculate(str_repeat('w ', 221), -5);
        $this->assertSame(2, $result['reading_time_min']);
    }

    public function testConfiguredZeroSpeedIsClampedToOne(): void
    {
        $config = $this->createStub(Config::class);
        $config->method('getReadingSpeedWpm')->willReturn(0);

        $result = (new ReadingTimeCalculator($config))->calculate('one two three', 0);
        $this->assertSame(3, $result['reading_time_min']);
    }
}
