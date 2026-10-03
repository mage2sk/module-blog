<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Url;

class SlugGenerator
{
    private const MAX_LENGTH = 80;

    private const STOP_WORDS = ['the', 'a', 'an', 'of', 'for', 'to', 'in', 'on', 'by', 'with', 'and', 'or'];

    private const VALID_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function __construct()
    {
    }

    public function generate(string $input, bool $keepStopWords = false): string
    {
        $value = strip_tags($input);
        $value = strtolower($value);

        $value = str_replace(["'", "\u{2019}"], '', $value);

        $value = (string) preg_replace('/[^a-z0-9]+/', '-', $value);

        $value = trim($value, '-');

        if ($value === '') {
            return '';
        }

        if (!$keepStopWords) {
            $segments = explode('-', $value);
            $filtered = [];
            foreach ($segments as $segment) {
                if ($segment === '' || in_array($segment, self::STOP_WORDS, true)) {
                    continue;
                }
                $filtered[] = $segment;
            }

            if ($filtered !== []) {
                $value = implode('-', $filtered);
            }
        }

        $value = (string) preg_replace('/-+/', '-', $value);
        $value = trim($value, '-');

        if (strlen($value) <= self::MAX_LENGTH) {
            return $value;
        }

        $truncated = substr($value, 0, self::MAX_LENGTH);
        $lastHyphen = strrpos($truncated, '-');
        if ($lastHyphen !== false && $lastHyphen > 0) {
            $truncated = substr($truncated, 0, $lastHyphen);
        }

        return trim($truncated, '-');
    }

    public function isValid(string $slug): bool
    {
        $length = strlen($slug);
        if ($length < 1 || $length > self::MAX_LENGTH) {
            return false;
        }

        return (bool) preg_match(self::VALID_PATTERN, $slug);
    }

    public function withSuffix(string $slug, int $n): string
    {
        $suffix = '-' . $n;
        $suffixLen = strlen($suffix);
        $maxBase = self::MAX_LENGTH - $suffixLen;

        if ($maxBase < 1) {
            return ltrim(substr($suffix, 0, self::MAX_LENGTH), '-');
        }

        if (strlen($slug) <= $maxBase) {
            return $slug . $suffix;
        }

        $truncated = substr($slug, 0, $maxBase);
        $lastHyphen = strrpos($truncated, '-');
        if ($lastHyphen !== false && $lastHyphen > 0) {
            $truncated = substr($truncated, 0, $lastHyphen);
        }
        $truncated = trim($truncated, '-');

        return $truncated . $suffix;
    }
}
