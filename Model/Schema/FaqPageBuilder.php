<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Schema;

class FaqPageBuilder
{
    public function build(array $faqs): ?array
    {
        if (empty($faqs)) {
            return null;
        }

        $mainEntity = [];
        foreach ($faqs as $faq) {
            $question = (string)($faq['question'] ?? '');
            $answer = (string)($faq['answer'] ?? '');
            if ($question === '' || $answer === '') {
                continue;
            }
            $mainEntity[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $answer,
                ],
            ];
        }

        if (empty($mainEntity)) {
            return null;
        }

        return [
            '@type' => 'FAQPage',
            'mainEntity' => $mainEntity,
        ];
    }
}
