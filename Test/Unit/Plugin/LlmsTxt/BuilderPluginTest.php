<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Plugin\LlmsTxt;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\LlmsTxt\BlogContributor;
use Panth\Blog\Model\Url\PostUrlBuilder;
use Panth\Blog\Plugin\LlmsTxt\BuilderPlugin;
use Panth\Blog\Plugin\LlmsTxt\JsonBuilderPlugin;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\TestCase;

class BuilderPluginTest extends TestCase
{
    use BlogTestHelpers;

    private function config(bool $txt, bool $full = false): Config
    {
        $config = $this->createStub(Config::class);
        $config->method('isLlmsTxtIncludeEnabled')->willReturn($txt);
        $config->method('isLlmsFullTxtIncludeEnabled')->willReturn($full);
        $config->method('getRouteFrontName')->willReturn('blog');
        $config->method('getLlmsTxtMaxPosts')->willReturn(5);
        return $config;
    }

    public function testAppendsSectionToLlmsTxt(): void
    {
        $contributor = $this->createStub(BlogContributor::class);
        $contributor->method('render')->willReturn("\n## Blog Posts\n- [A](/blog/a)\n");
        $plugin = new BuilderPlugin($contributor, $this->config(true));

        $this->assertSame("# Site\n\n## Blog Posts\n- [A](/blog/a)\n\n", $plugin->afterBuild(new \stdClass(), "# Site\n\n", 1));
    }

    public function testLeavesResultWhenDisabledEmptyOrFailing(): void
    {
        $contributor = $this->createMock(BlogContributor::class);
        $contributor->expects($this->never())->method('render');
        $this->assertSame('base', (new BuilderPlugin($contributor, $this->config(false)))->afterBuild(new \stdClass(), 'base', 1));

        $empty = $this->createStub(BlogContributor::class);
        $empty->method('render')->willReturn('');
        $this->assertSame('base', (new BuilderPlugin($empty, $this->config(true)))->afterBuild(new \stdClass(), 'base', 1));

        $failing = $this->createStub(BlogContributor::class);
        $failing->method('render')->willThrowException(new \RuntimeException('db'));
        $this->assertSame('base', (new BuilderPlugin($failing, $this->config(true)))->afterBuild(new \stdClass(), 'base', 1));
    }

    public function testFullBuilderUsesFullTxtSwitch(): void
    {
        $contributor = $this->createMock(BlogContributor::class);
        $contributor->expects($this->once())->method('render')->willReturn('SECTION');
        $plugin = new BuilderPlugin($contributor, $this->config(false, true));

        $this->assertSame("full\nSECTION\n", $plugin->afterBuild(new FullBuilder(), 'full', 1));
    }

    public function testFullBuilderGetsNothingFromRealContributorWhenLlmsTxtSwitchIsOff(): void
    {
        $config = $this->config(false, true);
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->never())->method('fetchAll');
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $contributor = new BlogContributor(
            $resource,
            $this->createStub(PostUrlBuilder::class),
            $this->createStub(\Panth\Blog\Model\Url\AuthorUrlBuilder::class),
            $config,
            $this->createStub(\Panth\Blog\Model\StoreVisibility::class)
        );

        $this->assertSame('full', (new BuilderPlugin($contributor, $config))->afterBuild(new FullBuilder(), 'full', 1));
    }

    private function jsonPlugin(bool $enabled, array $rows, bool $tableExists = true): JsonBuilderPlugin
    {
        $connection = $this->connectionStub();
        $connection->method('isTableExists')->willReturn($tableExists);
        $connection->method('fetchAll')->willReturn($rows);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        return new JsonBuilderPlugin($this->config($enabled), $resource, $this->createStub(PostUrlBuilder::class));
    }

    public function testJsonPluginAppendsBlogSection(): void
    {
        $plugin = $this->jsonPlugin(true, [
            ['url_key' => 'a', 'title' => ' Alpha ', 'short_description' => '<b>Hi</b>', 'published_at' => '2026-01-01'],
            ['url_key' => '', 'title' => 'Skip'],
        ]);
        $out = json_decode($plugin->afterBuild(null, '{"sections":[{"code":"cms"}]}', 1), true);

        $this->assertSame(['cms', 'blog'], array_column($out['sections'], 'code'));
        $blog = $out['sections'][1];
        $this->assertSame(1, $blog['count']);
        $this->assertSame([
            'url' => '/blog/a',
            'label' => 'Alpha',
            'type' => 'blog_post',
            'score' => 0.5,
            'summary' => 'Hi',
            'metadata' => ['published_at' => '2026-01-01'],
        ], $blog['entries'][0]);
    }

    public function testJsonPluginCreatesSectionsArrayWhenMissing(): void
    {
        $out = json_decode($this->jsonPlugin(true, [['url_key' => 'a', 'title' => 'A']])->afterBuild(null, '{"site":"x"}', 1), true);
        $this->assertSame('x', $out['site']);
        $this->assertCount(1, $out['sections']);
    }

    public function testJsonPluginLeavesResultAlone(): void
    {
        $this->assertSame('{}', $this->jsonPlugin(false, [['url_key' => 'a', 'title' => 'A']])->afterBuild(null, '{}', 1));
        $this->assertSame('not json', $this->jsonPlugin(true, [['url_key' => 'a', 'title' => 'A']])->afterBuild(null, 'not json', 1));
        $this->assertSame('{}', $this->jsonPlugin(true, [])->afterBuild(null, '{}', 1));
        $this->assertSame('{}', $this->jsonPlugin(true, [['url_key' => 'a', 'title' => 'A']], false)->afterBuild(null, '{}', 1));
    }
}
