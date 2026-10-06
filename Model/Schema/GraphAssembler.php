<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Schema;

class GraphAssembler
{
    public function assemble(array $entities): string
    {
        $graph = [];
        foreach ($entities as $entity) {
            if ($entity === null) {
                continue;
            }
            $cleaned = $this->stripNulls($entity);
            if ($cleaned === null || $cleaned === []) {
                continue;
            }
            $graph[] = $cleaned;
        }

        $payload = [
            '@context' => 'https://schema.org',
            '@graph' => array_values($graph),
        ];

        return (string)json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP
        );
    }

    private function stripNulls(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $result = [];
        $isList = array_is_list($value);
        foreach ($value as $k => $v) {
            if ($v === null) {
                continue;
            }
            $cleaned = $this->stripNulls($v);
            if ($cleaned === null) {
                continue;
            }
            if (is_array($cleaned) && $cleaned === []) {
                continue;
            }
            if ($isList) {
                $result[] = $cleaned;
            } else {
                $result[$k] = $cleaned;
            }
        }

        return $result;
    }
}
