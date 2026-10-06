<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Reader;

use Panth\Blog\Helper\Config;

class ReadingTimeCalculator
{
    public function __construct(
        private readonly ?Config $config = null
    ) {
    }

    public function calculate(string $html, int $wpm = 220): array
    {
        if ($wpm <= 0) {
            $wpm = $this->config !== null ? max(1, $this->config->getReadingSpeedWpm()) : 220;
        }
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $wordCount = is_array($words) ? count($words) : 0;
        $time = max(1, (int) ceil($wordCount / max(1, $wpm)));
        return [
            'word_count'       => $wordCount,
            'reading_time_min' => $time,
        ];
    }
}
