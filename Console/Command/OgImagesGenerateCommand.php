<?php
declare(strict_types=1);

namespace Panth\Blog\Console\Command;

use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class OgImagesGenerateCommand extends Command
{
    private const OPT_POST_ID = 'post-id';

    public function __construct(
        private readonly State $appState
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('panth:blog:og-images:generate');
        $this->setDescription('Generate OG/social images for blog posts (stub in v1).');
        $this->addOption(self::OPT_POST_ID, null, InputOption::VALUE_OPTIONAL, 'Limit to one post ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode('adminhtml');
        } catch (\Throwable) {
        }

        $output->writeln('OG image generation not implemented in v1 - run later via cron.');
        return 0;
    }
}
