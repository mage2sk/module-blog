<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Text;

class Truncator
{
    private const ELLIPSIS = '...';

    public function truncate(string $value, int $max): string
    {
        if ($value === '' || $max <= 3) {
            return $value;
        }

        $budget = $max - 3;

        if (function_exists('mb_strlen')) {
            if (mb_strlen($value, 'UTF-8') <= $max) {
                return $value;
            }

            return rtrim(mb_substr($value, 0, $budget, 'UTF-8')) . self::ELLIPSIS;
        }

        if (strlen($value) <= $max) {
            return $value;
        }

        return rtrim(substr($value, 0, $budget)) . self::ELLIPSIS;
    }
}
