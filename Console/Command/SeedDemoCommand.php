<?php
declare(strict_types=1);

namespace Panth\Blog\Console\Command;

use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\Blog\Api\AuthorRepositoryInterface;
use Panth\Blog\Api\CategoryRepositoryInterface;
use Panth\Blog\Api\Data\AuthorInterface;
use Panth\Blog\Api\Data\AuthorInterfaceFactory;
use Panth\Blog\Api\Data\CategoryInterface;
use Panth\Blog\Api\Data\CategoryInterfaceFactory;
use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Api\Data\PostInterfaceFactory;
use Panth\Blog\Api\Data\TagInterface;
use Panth\Blog\Api\Data\TagInterfaceFactory;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Api\TagRepositoryInterface;
use Panth\Blog\Model\ResourceModel\Post as PostResource;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SeedDemoCommand extends Command
{
    public function __construct(
        private readonly State $appState,
        private readonly AuthorRepositoryInterface $authorRepository,
        private readonly AuthorInterfaceFactory $authorFactory,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly CategoryInterfaceFactory $categoryFactory,
        private readonly TagRepositoryInterface $tagRepository,
        private readonly TagInterfaceFactory $tagFactory,
        private readonly PostRepositoryInterface $postRepository,
        private readonly PostInterfaceFactory $postFactory,
        private readonly PostResource $postResource
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('panth:blog:seed:demo')
            ->setDescription('Seed 5 demo authors / categories / tags / posts for local testing.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode('adminhtml');
        } catch (\Throwable) {
        }

        $output->writeln('<info>Seeding Panth_Blog demo data...</info>');

        $authors = $this->seedAuthors($output);
        $categories = $this->seedCategories($output);
        $tags = $this->seedTags($output);
        $this->seedPosts($output, $authors, $categories, $tags);

        $output->writeln('<info>Done.</info>');
        return Command::SUCCESS;
    }

    private function seedAuthors(OutputInterface $output): array
    {
        $seed = [
            ['url_key' => 'demo-jane-doe', 'display_name' => 'Jane Doe', 'role' => 'Senior Editor', 'short_bio' => 'Covers Magento performance + Hyva.', 'sort_order' => 10],
            ['url_key' => 'demo-john-smith', 'display_name' => 'John Smith', 'role' => 'Technical Writer', 'short_bio' => 'Writes about AI in commerce.', 'sort_order' => 20],
            ['url_key' => 'demo-alice-keene', 'display_name' => 'Alice Keene', 'role' => 'SEO Lead', 'short_bio' => 'Long-form SEO + structured data.', 'sort_order' => 30],
            ['url_key' => 'demo-marco-rossi', 'display_name' => 'Marco Rossi', 'role' => 'Front-end Engineer', 'short_bio' => 'Tailwind + Alpine.js patterns.', 'sort_order' => 40],
            ['url_key' => 'demo-priya-shah', 'display_name' => 'Priya Shah', 'role' => 'Solutions Architect', 'short_bio' => 'Headless Magento + GraphQL.', 'sort_order' => 50],
        ];
        $out = [];
        foreach ($seed as $row) {
            $author = $this->loadByUrlKey(fn() => $this->authorRepository->getByUrlKey($row['url_key']));
            if (!$author) {
                $author = $this->authorFactory->create();
                $author->setData($row);
                $author->setData(AuthorInterface::IS_ACTIVE, 1);
                $author = $this->authorRepository->save($author);
                $output->writeln('  + author: ' . $row['display_name'] . ' (id=' . $author->getAuthorId() . ')');
            } else {
                $output->writeln('  = author exists: ' . $row['display_name']);
            }
            $out[] = $author;
        }
        return $out;
    }

    private function seedCategories(OutputInterface $output): array
    {
        $seed = [
            ['url_key' => 'demo-magento', 'name' => 'Magento', 'description' => 'Magento Open Source topics.', 'sort_order' => 10],
            ['url_key' => 'demo-hyva', 'name' => 'Hyva', 'description' => 'Hyva theme + Tailwind articles.', 'sort_order' => 20],
            ['url_key' => 'demo-seo', 'name' => 'SEO', 'description' => 'On-page + technical SEO.', 'sort_order' => 30],
            ['url_key' => 'demo-performance', 'name' => 'Performance', 'description' => 'Core Web Vitals + caching.', 'sort_order' => 40],
            ['url_key' => 'demo-ai', 'name' => 'AI', 'description' => 'AI in commerce.', 'sort_order' => 50],
        ];
        $out = [];
        foreach ($seed as $row) {
            $cat = $this->loadByUrlKey(fn() => $this->categoryRepository->getByUrlKey($row['url_key']));
            if (!$cat) {
                $cat = $this->categoryFactory->create();
                $cat->setData($row);
                $cat->setData(CategoryInterface::IS_ACTIVE, 1);
                $cat->setData(CategoryInterface::LEVEL, 1);
                $cat->setData(CategoryInterface::TEMPLATE, 'grid');
                $cat = $this->categoryRepository->save($cat);
                $output->writeln('  + category: ' . $row['name'] . ' (id=' . $cat->getCategoryId() . ')');
            } else {
                $output->writeln('  = category exists: ' . $row['name']);
            }
            $out[] = $cat;
        }
        return $out;
    }

    private function seedTags(OutputInterface $output): array
    {
        $seed = [
            ['url_key' => 'demo-magento-2-4-8', 'name' => 'magento-2.4.8'],
            ['url_key' => 'demo-tailwind', 'name' => 'tailwind'],
            ['url_key' => 'demo-graphql', 'name' => 'graphql'],
            ['url_key' => 'demo-core-web-vitals', 'name' => 'core-web-vitals'],
            ['url_key' => 'demo-chatgpt', 'name' => 'chatgpt'],
        ];
        $out = [];
        foreach ($seed as $row) {
            $tag = $this->loadByUrlKey(fn() => $this->tagRepository->getByUrlKey($row['url_key']));
            if (!$tag) {
                $tag = $this->tagFactory->create();
                $tag->setData($row);
                $tag = $this->tagRepository->save($tag);
                $output->writeln('  + tag: ' . $row['name'] . ' (id=' . $tag->getTagId() . ')');
            } else {
                $output->writeln('  = tag exists: ' . $row['name']);
            }
            $out[] = $tag;
        }
        return $out;
    }

    private function seedPosts(OutputInterface $output, array $authors, array $categories, array $tags): void
    {
        $now = date('Y-m-d H:i:s');
        $seed = [
            [
                'url_key' => 'demo-getting-started-with-hyva',
                'title' => 'Getting started with Hyva',
                'short_description' => 'A practical walkthrough of installing Hyva and shipping your first storefront tweak.',
                'content' => "<p>Hyva is a Tailwind-based front-end for Magento that replaces the Luma + RequireJS stack with a leaner runtime.</p><h2 id=\"why-hyva\">Why Hyva</h2><p>Smaller bundle, faster TTI, easier Tailwind workflows.</p><h2 id=\"installing\">Installing Hyva</h2><p>Run <code>composer require hyva-themes/magento2-default-theme</code> then enable the theme in admin.</p><h2 id=\"first-tweak\">Your first tweak</h2><p>Edit a phtml under <code>app/design/frontend/Hyva/yourtheme/...</code> and reload.</p>",
                'category_idx' => 1, 'tag_idxs' => [1, 0],
            ],
            [
                'url_key' => 'demo-core-web-vitals-2026',
                'title' => 'Core Web Vitals for Magento in 2026',
                'short_description' => 'INP replaced FID. Here is what to measure and how to fix the common Magento regressions.',
                'content' => "<p>Core Web Vitals are the field-data metrics Google uses to rank pages.</p><h2 id=\"inp\">INP basics</h2><p>Interaction to Next Paint should be under 200ms on mobile.</p><h2 id=\"fixes\">Common fixes</h2><p>Defer non-critical JS, split chunks, lazy-load below-fold images.</p>",
                'category_idx' => 3, 'tag_idxs' => [3, 0],
            ],
            [
                'url_key' => 'demo-graphql-vs-rest',
                'title' => 'GraphQL vs REST for Magento storefronts',
                'short_description' => 'When to pick GraphQL, when REST is still the right call, and how to mix both.',
                'content' => "<p>Magento ships both REST and GraphQL. Pick by query shape, not hype.</p><h2 id=\"when-graphql\">When GraphQL wins</h2><p>Composable PDP queries that need 30 fields from 5 services.</p><h2 id=\"when-rest\">When REST wins</h2><p>Admin imports, batch writes, file uploads.</p>",
                'category_idx' => 0, 'tag_idxs' => [2, 0],
            ],
            [
                'url_key' => 'demo-tailwind-tips',
                'title' => 'Tailwind tips for Magento themes',
                'short_description' => 'Five Tailwind patterns that pay off in Hyva projects.',
                'content' => "<p>Tailwind utilities scale well when paired with a small set of component classes.</p><h2 id=\"pattern-1\">Use the prose plugin</h2><p>Great defaults for CMS content.</p><h2 id=\"pattern-2\">Custom variants for state</h2><p>Group hover, focus-within, peer states.</p>",
                'category_idx' => 1, 'tag_idxs' => [1],
            ],
            [
                'url_key' => 'demo-chatgpt-for-merchants',
                'title' => 'How merchants are using ChatGPT in 2026',
                'short_description' => 'Practical workflows for product descriptions, FAQs, and meta tags that move metrics.',
                'content' => "<p>Generative AI is now table stakes for catalog teams.</p><h2 id=\"workflow-1\">Product descriptions</h2><p>Provide spec sheets + brand voice + 2 examples; iterate.</p><h2 id=\"workflow-2\">Meta tags</h2><p>Pull search-query data, group by intent, generate per cluster.</p>",
                'category_idx' => 4, 'tag_idxs' => [4, 2],
            ],
        ];

        foreach ($seed as $i => $row) {
            $post = $this->loadByUrlKey(fn() => $this->postRepository->getByUrlKey($row['url_key']));
            if (!$post) {
                $author = $authors[$i % count($authors)];
                $post = $this->postFactory->create();
                $post->setData(PostInterface::URL_KEY, $row['url_key']);
                $post->setData(PostInterface::TITLE, $row['title']);
                $post->setData(PostInterface::SHORT_DESCRIPTION, $row['short_description']);
                $post->setData(PostInterface::CONTENT, $row['content']);
                $post->setData(PostInterface::STATUS, 'published');
                $post->setData(PostInterface::AUTHOR_ID, (int)$author->getAuthorId());
                $post->setData(PostInterface::PUBLISHED_AT, $now);
                $post->setData(PostInterface::META_ROBOTS, 'index,follow');
                $post->setData(PostInterface::ENABLE_TOC, 1);
                $post->setData(PostInterface::ENABLE_COMMENTS, 1);
                $post = $this->postRepository->save($post);

                $catId = (int)$categories[$row['category_idx']]->getCategoryId();
                $this->postResource->saveCategoryLinks((int)$post->getPostId(), [$catId], $catId);

                $tagIds = array_map(static fn(int $idx) => (int)$tags[$idx]->getTagId(), $row['tag_idxs']);
                $this->postResource->saveTagLinks((int)$post->getPostId(), $tagIds);

                $this->postResource->saveStoreLinks((int)$post->getPostId(), [0]);

                $output->writeln('  + post: ' . $row['title'] . ' (id=' . $post->getPostId() . ')');
            } else {
                $output->writeln('  = post exists: ' . $row['title']);
            }
        }
    }

    private function loadByUrlKey(callable $loader): mixed
    {
        try {
            return $loader();
        } catch (NoSuchEntityException) {
            return null;
        }
    }
}
