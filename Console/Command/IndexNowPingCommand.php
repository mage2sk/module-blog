<?php
declare(strict_types=1);

namespace Panth\Blog\Console\Command;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Model\Url\PostUrlBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class IndexNowPingCommand extends Command
{
    private const OPT_POST_ID = 'post-id';

    public function __construct(
        private readonly State $appState,
        private readonly ResourceConnection $resource,
        private readonly PostRepositoryInterface $postRepository,
        private readonly PostUrlBuilder $postUrlBuilder,
        private readonly StoreManagerInterface $storeManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('panth:blog:indexnow:ping');
        $this->setDescription('Enqueue a blog post URL into the IndexNow queue.');
        $this->addOption(self::OPT_POST_ID, null, InputOption::VALUE_REQUIRED, 'Post ID to enqueue');
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
                $output->writeln('<error>--post-id is required and must be positive.</error>');
                return 1;
            }

            $conn = $this->resource->getConnection();
            $queueTable = $this->resource->getTableName('panth_blog_indexnow_queue');
            if (!$conn->isTableExists($queueTable)) {
                $output->writeln('<error>IndexNow queue table missing.</error>');
                return 1;
            }

            $post = $this->postRepository->getById($postId);
            $storeId = (int) $this->storeManager->getDefaultStoreView()?->getId();
            $url = $this->postUrlBuilder->getPostUrl($post);

            $conn->insert($queueTable, [
                'url'       => $url,
                'store_id'  => $storeId,
                'status'    => 'pending',
            ]);

            $output->writeln('<info>Queued: ' . $url . '</info>');
            return 0;
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return 1;
        }
    }
}
