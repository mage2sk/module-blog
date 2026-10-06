<?php
declare(strict_types=1);

namespace Panth\Blog\Plugin\LlmsTxt;

use Panth\Blog\Helper\Config;
use Panth\Blog\Model\LlmsTxt\BlogContributor;

class BuilderPlugin
{
    public function __construct(
        private readonly BlogContributor $contributor,
        private readonly Config $config
    ) {
    }

    public function afterBuild($subject, string $result, int $storeId): string
    {
        $subjectClass = get_class($subject);
        $isFull = str_contains($subjectClass, '\\FullBuilder');
        $enabled = $isFull
            ? $this->config->isLlmsFullTxtIncludeEnabled($storeId)
            : $this->config->isLlmsTxtIncludeEnabled($storeId);
        if (!$enabled) {
            return $result;
        }

        try {
            $section = $this->contributor->render($storeId);
        } catch (\Throwable) {
            return $result;
        }

        if ($section === '') {
            return $result;
        }

        return rtrim($result) . "\n" . $section . "\n";
    }
}
