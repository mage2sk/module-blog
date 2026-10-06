<?php
declare(strict_types=1);

namespace Panth\Blog\Console\Command;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Api\Data\PostInterfaceFactory;
use Panth\Blog\Api\PostRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PostImportCommand extends Command
{
    private const ARG_FILE = 'file';

    public function __construct(
        private readonly State $appState,
        private readonly PostRepositoryInterface $postRepository,
        private readonly PostInterfaceFactory $postFactory,
        private readonly ResourceConnection $resource
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('panth:blog:post:import');
        $this->setDescription('Import a Markdown file with YAML frontmatter as a new blog post.');
        $this->addArgument(self::ARG_FILE, InputArgument::REQUIRED, 'Path to the .md file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode('adminhtml');
        } catch (\Throwable) {
        }

        try {
            $file = (string) $input->getArgument(self::ARG_FILE);
            if ($file === '' || !is_readable($file)) {
                $output->writeln('<error>File not readable: ' . $file . '</error>');
                return 1;
            }

            $raw = (string) file_get_contents($file);
            if ($raw === '') {
                $output->writeln('<error>File is empty.</error>');
                return 1;
            }

            $frontmatter = [];
            $body = $raw;
            if (preg_match('/^---\n(.*?)\n---\n(.*)$/s', $raw, $matches)) {
                $frontmatter = $this->parseFrontmatter($matches[1]);
                $body = $matches[2];
            }

            $title = (string) ($frontmatter['title'] ?? '');
            if ($title === '') {
                $output->writeln('<error>Frontmatter must include a "title".</error>');
                return 1;
            }

            $urlKey = (string) ($frontmatter['url_key'] ?? '');
            if ($urlKey === '') {
                $urlKey = $this->slugify($title);
            }

            $post = $this->postFactory->create();
            $post->setTitle($title);
            $post->setUrlKey($urlKey);
            $post->setStatus((string) ($frontmatter['status'] ?? PostInterface::STATUS_DRAFT));
            $post->setShortDescription((string) ($frontmatter['short_description'] ?? '') ?: null);
            $post->setContent($body);

            if (!empty($frontmatter['published_at'])) {
                $post->setPublishedAt((string) $frontmatter['published_at']);
            }

            if (!empty($frontmatter['author'])) {
                $authorId = $this->lookupAuthorId((string) $frontmatter['author']);
                if ($authorId !== null) {
                    $post->setAuthorId($authorId);
                }
            }

            $saved = $this->postRepository->save($post);

            $tags = $this->splitCsv((string) ($frontmatter['tags'] ?? ''));
            $categories = $this->splitCsv((string) ($frontmatter['categories'] ?? ''));
            $this->attachTags((int) $saved->getPostId(), $tags);
            $this->attachCategories((int) $saved->getPostId(), $categories);

            $output->writeln('<info>Imported post #' . (int) $saved->getPostId() . ' (' . $saved->getUrlKey() . ').</info>');
            return 0;
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return 1;
        }
    }

    private function parseFrontmatter(string $yaml): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', $yaml) ?: [] as $line) {
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (!preg_match('/^([a-zA-Z0-9_]+):\s*(.*)$/', $line, $m)) {
                continue;
            }
            $key = $m[1];
            $value = trim($m[2]);
            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $decoded = json_decode($value, true);
                if (is_string($decoded)) {
                    $value = $decoded;
                } else {
                    $value = trim($value, "\"'");
                }
            }
            $out[$key] = $value;
        }
        return $out;
    }

    private function slugify(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
        $value = trim($value, '-');
        return substr($value, 0, 80);
    }

    private function splitCsv(string $value): array
    {
        if ($value === '') {
            return [];
        }
        $parts = array_map('trim', explode(',', $value));
        return array_values(array_filter($parts, static fn ($p) => $p !== ''));
    }

    private function lookupAuthorId(string $urlKey): ?int
    {
        $conn = $this->resource->getConnection();
        $table = $this->resource->getTableName('panth_blog_author');
        if (!$conn->isTableExists($table)) {
            return null;
        }
        $id = $conn->fetchOne(
            $conn->select()->from($table, ['author_id'])->where('url_key = ?', $urlKey)
        );
        return $id !== false && $id !== '' ? (int) $id : null;
    }

    private function attachTags(int $postId, array $tags): void
    {
        if ($postId === 0 || $tags === []) {
            return;
        }
        $conn = $this->resource->getConnection();
        $tagTable = $this->resource->getTableName('panth_blog_tag');
        $linkTable = $this->resource->getTableName('panth_blog_post_tag');
        if (!$conn->isTableExists($tagTable) || !$conn->isTableExists($linkTable)) {
            return;
        }
        foreach ($tags as $tagName) {
            $urlKey = $this->slugify($tagName);
            if ($urlKey === '') {
                continue;
            }
            $existing = $conn->fetchOne(
                $conn->select()->from($tagTable, ['tag_id'])->where('url_key = ?', $urlKey)
            );
            if ($existing) {
                $tagId = (int) $existing;
            } else {
                $conn->insert($tagTable, [
                    'url_key'   => $urlKey,
                    'name'      => $tagName,
                ]);
                $tagId = (int) $conn->lastInsertId($tagTable);
            }
            if ($tagId > 0) {
                $conn->insertOnDuplicate($linkTable, [
                    'post_id' => $postId,
                    'tag_id'  => $tagId,
                ], ['post_id']);
            }
        }
    }

    private function attachCategories(int $postId, array $categories): void
    {
        if ($postId === 0 || $categories === []) {
            return;
        }
        $conn = $this->resource->getConnection();
        $catTable = $this->resource->getTableName('panth_blog_category');
        $linkTable = $this->resource->getTableName('panth_blog_post_category');
        if (!$conn->isTableExists($catTable) || !$conn->isTableExists($linkTable)) {
            return;
        }
        $isPrimary = 1;
        foreach ($categories as $catName) {
            $urlKey = $this->slugify($catName);
            if ($urlKey === '') {
                continue;
            }
            $catId = (int) $conn->fetchOne(
                $conn->select()->from($catTable, ['category_id'])->where('url_key = ?', $urlKey)
            );
            if ($catId === 0) {
                continue;
            }
            $conn->insertOnDuplicate($linkTable, [
                'post_id'     => $postId,
                'category_id' => $catId,
                'position'    => 0,
                'is_primary'  => $isPrimary,
            ], ['position']);
            $isPrimary = 0;
        }
    }
}
