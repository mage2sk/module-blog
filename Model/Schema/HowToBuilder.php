<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Schema;

class HowToBuilder
{
    public function extractFromContent(string $html, string $name = ''): ?array
    {
        if ($html === '') {
            return null;
        }

        $pattern = '/<h2[^>]*>\s*(?:Step\s*)?(\d+)\.?\s+(.+?)<\/h2>/i';
        if (!preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            return null;
        }

        if (count($matches) < 2) {
            return null;
        }

        $steps = [];
        foreach ($matches as $m) {
            $stepName = trim(strip_tags($m[2]));
            if ($stepName === '') {
                continue;
            }
            $steps[] = [
                '@type' => 'HowToStep',
                'name' => $stepName,
            ];
        }

        if (count($steps) < 2) {
            return null;
        }

        return [
            '@type' => 'HowTo',
            'name' => $name,
            'step' => $steps,
        ];
    }
}
