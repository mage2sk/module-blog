<?php
declare(strict_types=1);

namespace Panth\Blog\Console\Command;

use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CountsRefreshCommand extends Command
{
    public function __construct(
        private readonly State $appState,
        private readonly \Panth\Blog\Cron\RefreshPostCounts $refreshPostCounts
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('panth:blog:counts:refresh');
        $this->setDescription('Refresh denormalised post_count on categories, tags, and authors.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode('adminhtml');
        } catch (\Throwable) {
        }

        try {
            $this->refreshPostCounts->execute();
            $output->writeln('<info>Post counts refreshed.</info>');
            return 0;
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return 1;
        }
    }
}
