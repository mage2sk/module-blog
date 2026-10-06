<?php
declare(strict_types=1);

namespace Panth\Blog\Service;

class InlineFaqExtractor
{
    public function extract(string $html): array
    {
        if ($html === '' || stripos($html, 'pb-faq') === false) {
            return [];
        }

        $faqs = [];
        if (!preg_match_all('#<dl[^>]*class="[^"]*pb-faq[^"]*"[^>]*>(.*?)</dl>#is', $html, $blocks)) {
            return [];
        }

        foreach ($blocks[1] as $block) {
            if (!preg_match_all('#<dt[^>]*>(.*?)</dt>\s*<dd[^>]*>(.*?)</dd>#is', $block, $pairs, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($pairs as $pair) {
                $question = trim(strip_tags($pair[1]));
                $answer = trim(strip_tags($pair[2]));
                if ($question === '' || $answer === '') {
                    continue;
                }
                $faqs[] = [
                    'question' => $question,
                    'answer'   => $answer,
                ];
            }
        }

        return $faqs;
    }
}
