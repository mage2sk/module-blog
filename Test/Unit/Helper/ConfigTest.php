<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Helper\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private function config(array $values): Config
    {
        $scope = $this->createStub(ScopeConfigInterface::class);
        $scope->method('getValue')->willReturnCallback(
            static fn (string $path) => $values[$path] ?? null
        );

        return new Config($scope, $this->createStub(StoreManagerInterface::class));
    }

    public function testGetValuePrefixesPathAndPassesStoreScope(): void
    {
        $scope = $this->createMock(ScopeConfigInterface::class);
        $scope->expects($this->once())
            ->method('getValue')
            ->with('panth_blog/general/enabled', ScopeInterface::SCOPE_STORE, 3)
            ->willReturn('1');

        $config = new Config($scope, $this->createStub(StoreManagerInterface::class));
        $this->assertTrue($config->isEnabled(3));
    }

    public static function defaultsProvider(): array
    {
        return [
            'route' => ['getRouteFrontName', 'blog'],
            'posts per page' => ['getPostsPerPage', 10],
            'wpm' => ['getReadingSpeedWpm', 220],
            'toc min h2' => ['getTocMinH2', 3],
            'category template' => ['getCategoryTemplate', 'grid'],
            'posts per feed' => ['getFeedsPostsPerFeed', 25],
            'feed ttl' => ['getFeedsCacheTtl', 3600],
            'tag thin' => ['getTagThinThreshold', 3],
            'og color' => ['getOgImageBrandColor', '#f97316'],
            'indexnow batch' => ['getIndexNowBatchSize', 100],
            'comments allow for' => ['getCommentsAllowFor', 'everyone'],
            'perf ttl' => ['getPerfCacheTtl', 7200],
            'url history' => ['getUrlHistoryRetentionDays', 365],
            'sender identity' => ['getCommentNotifySenderIdentity', 'general'],
            'responsive sizes' => ['getResponsiveSizes', [480, 800, 1200]],
            'related count' => ['getRelatedPostsCount', 0],
            'enabled' => ['isEnabled', false],
        ];
    }

    #[DataProvider('defaultsProvider')]
    public function testDefaultsWhenUnset(string $method, mixed $expected): void
    {
        $this->assertSame($expected, $this->config([])->{$method}());
    }

    public static function configuredProvider(): array
    {
        return [
            ['getRouteFrontName', 'general/route_frontname', 'news', 'news'],
            ['getPostsPerPage', 'general/posts_per_page', '7', 7],
            ['getReadingSpeedWpm', 'general/reading_speed_wpm', '300', 300],
            ['getCategoryTemplate', 'display/category_template', 'list', 'list'],
            ['getOgImageBrandColor', 'seo/og_image_brand_color', '#000000', '#000000'],
            ['getCommentsAllowFor', 'comments/allow_for', 'registered', 'registered'],
            ['isFeedEnabled', 'feeds/enabled', '1', true],
            ['isShowToc', 'display/show_toc', '0', false],
            ['getIndexTitle', 'general/index_title', 'Journal', 'Journal'],
            ['getNewBadgeDays', 'display/new_badge_days', '14', 14],
        ];
    }

    #[DataProvider('configuredProvider')]
    public function testConfiguredValues(string $method, string $path, string $raw, mixed $expected): void
    {
        $this->assertSame($expected, $this->config(['panth_blog/' . $path => $raw])->{$method}());
    }

    public function testResponsiveSizesSkipNonNumericParts(): void
    {
        $config = $this->config(['panth_blog/perf/responsive_image_sizes' => '320, abc, 640,-5, 1024px,1600']);
        $this->assertSame([320, 640, 1600], $config->getResponsiveSizes());
    }

    public function testResponsiveSizesFallBackWhenNothingValid(): void
    {
        $config = $this->config(['panth_blog/perf/responsive_image_sizes' => 'big, small']);
        $this->assertSame([480, 800, 1200], $config->getResponsiveSizes());
    }

    public function testCommentsMaxPerHourIsNeverNegative(): void
    {
        $this->assertSame(0, $this->config(['panth_blog/comments/max_per_hour_per_ip' => '-4'])->getCommentsMaxPerHourPerIp());
        $this->assertSame(5, $this->config(['panth_blog/comments/max_per_hour_per_ip' => '5'])->getCommentsMaxPerHourPerIp());
    }

    public function testNotifyRecipientPrefersConfiguredValue(): void
    {
        $config = $this->config([
            'panth_blog/comments/notify_recipient_email' => '  editor@example.com ',
            'trans_email/ident_general/email' => 'general@example.com',
        ]);
        $this->assertSame('editor@example.com', $config->getCommentNotifyRecipient());
    }

    public function testNotifyRecipientFallsBackToGeneralContact(): void
    {
        $config = $this->config(['trans_email/ident_general/email' => ' general@example.com ']);
        $this->assertSame('general@example.com', $config->getCommentNotifyRecipient());
    }

    public function testNotifyRecipientEmptyWhenNothingConfigured(): void
    {
        $this->assertSame('', $this->config([])->getCommentNotifyRecipient());
    }
}
