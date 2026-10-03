<?php
declare(strict_types=1);

namespace Panth\Blog\Console\Command;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Panth\Blog\Helper\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TagsAutoRobotsCommand extends Command
{
    public function __construct(
        private readonly State $appState,
        private readonly ResourceConnection $resource,
        private readonly Config $config
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('panth:blog:tags:auto-robots');
        $this->setDescription('Auto-flip tag meta_robots to noindex,follow when post_count is below threshold.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode('adminhtml');
        } catch (\Throwable) {
        }

        try {
            $conn = $this->resource->getConnection();
            $table = $this->resource->getTableName('panth_blog_tag');
            if (!$conn->isTableExists($table)) {
                $output->writeln('<error>panth_blog_tag table missing.</error>');
                return 1;
            }

            $threshold = $this->config->getTagThinThreshold();

            $thin = $conn->update(
                $table,
                ['meta_robots' => 'noindex,follow'],
                ['post_count < ?' => $threshold]
            );
            $healthy = $conn->update(
                $table,
                ['meta_robots' => 'index,follow'],
                ['post_count >= ?' => $threshold]
            );

            $output->writeln(
                '<info>Thin tags set to noindex: ' . (int) $thin
                . '; healthy tags set to index: ' . (int) $healthy
                . ' (threshold: ' . $threshold . ').</info>'
            );
            return 0;
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return 1;
        }
    }
}
