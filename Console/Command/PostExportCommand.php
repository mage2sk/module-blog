<?php
declare(strict_types=1);

namespace Panth\Blog\Console\Command;

use Magento\Framework\App\State;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Service\MarkdownExporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PostExportCommand extends Command
{
    private const OPT_POST_ID = 'post-id';
    private const OPT_FORMAT  = 'format';

    public function __construct(
        private readonly State $appState,
        private readonly PostRepositoryInterface $postRepository,
        private readonly MarkdownExporter $markdownExporter
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('panth:blog:post:export');
        $this->setDescription('Export a blog post to Markdown (stdout).');
        $this->addOption(self::OPT_POST_ID, null, InputOption::VALUE_REQUIRED, 'Post ID to export');
        $this->addOption(self::OPT_FORMAT, null, InputOption::VALUE_OPTIONAL, 'Output format', 'markdown');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode('adminhtml');
        } catch (\Throwable) {
        }

        try {
            $postId = (int) $input->getOption(self::OPT_POST_ID);
            if ($postId <= 0) {
                $output->writeln('<error>--post-id is required and must be a positive integer.</error>');
                return 1;
            }

            $format = (string) $input->getOption(self::OPT_FORMAT);
            if ($format !== 'markdown') {
                $output->writeln('<error>Only --format=markdown is supported in v1.</error>');
                return 1;
            }

            $post = $this->postRepository->getById($postId);
            $output->write($this->markdownExporter->toMarkdown($post));
            return 0;
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return 1;
        }
    }
}
