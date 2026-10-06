<?php
declare(strict_types=1);

namespace Panth\Blog\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Config
{
    public const XML_PATH_PREFIX = 'panth_blog/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return (bool) $this->getValue('general/enabled', $storeId);
    }

    public function getRouteFrontName(): string
    {
        $value = (string) $this->getValue('general/route_frontname');
        return $value !== '' ? $value : 'blog';
    }

    public function getIndexTitle(?int $storeId = null): string
    {
        return (string) $this->getValue('general/index_title', $storeId);
    }

    public function getIndexDescription(?int $storeId = null): string
    {
        return (string) $this->getValue('general/index_description', $storeId);
    }

    public function getIndexHeroText(?int $storeId = null): string
    {
        return (string) $this->getValue('general/index_hero_text', $storeId);
    }

    public function getPostsPerPage(?int $storeId = null): int
    {
        return (int) $this->getValue('general/posts_per_page', $storeId) ?: 10;
    }

    public function getReadingSpeedWpm(): int
    {
        return (int) $this->getValue('general/reading_speed_wpm') ?: 220;
    }

    public function isShowReadingTime(?int $storeId = null): bool
    {
        return (bool) $this->getValue('general/show_reading_time', $storeId);
    }

    public function isShowAuthorBio(?int $storeId = null): bool
    {
        return (bool) $this->getValue('general/show_author_bio', $storeId);
    }

    public function isShowShareButtons(?int $storeId = null): bool
    {
        return (bool) $this->getValue('general/show_share_buttons', $storeId);
    }

    public function isShowToc(?int $storeId = null): bool
    {
        return (bool) $this->getValue('display/show_toc', $storeId);
    }

    public function getTocMinH2(?int $storeId = null): int
    {
        return (int) $this->getValue('display/toc_min_h2', $storeId) ?: 3;
    }

    public function getRelatedPostsCount(?int $storeId = null): int
    {
        return (int) $this->getValue('display/related_posts_count', $storeId);
    }

    public function getRelatedPostsStrategy(?int $storeId = null): string
    {
        return (string) $this->getValue('display/related_posts_strategy', $storeId);
    }

    public function getSidebarRecentCount(?int $storeId = null): int
    {
        return (int) $this->getValue('display/sidebar_recent_count', $storeId);
    }

    public function isSidebarShowCategories(?int $storeId = null): bool
    {
        return (bool) $this->getValue('display/sidebar_show_categories', $storeId);
    }

    public function isSidebarShowTagCloud(?int $storeId = null): bool
    {
        return (bool) $this->getValue('display/sidebar_show_tag_cloud', $storeId);
    }

    public function isSidebarShowSearch(?int $storeId = null): bool
    {
        return (bool) $this->getValue('display/sidebar_show_search', $storeId);
    }

    public function isSidebarShowSubscribe(?int $storeId = null): bool
    {
        return (bool) $this->getValue('display/sidebar_show_subscribe', $storeId);
    }

    public function getCategoryTemplate(?int $storeId = null): string
    {
        $value = (string) $this->getValue('display/category_template', $storeId);
        return $value !== '' ? $value : 'grid';
    }

    public function isShowBreadcrumbs(?int $storeId = null): bool
    {
        return (bool) $this->getValue('display/show_breadcrumbs', $storeId);
    }

    public function getPostExcerptChars(?int $storeId = null): int
    {
        return (int) $this->getValue('display/post_excerpt_chars', $storeId);
    }

    public function isFeedEnabled(?int $storeId = null): bool
    {
        return (bool) $this->getValue('feeds/enabled', $storeId);
    }

    public function getFeedsPostsPerFeed(?int $storeId = null): int
    {
        return (int) $this->getValue('feeds/posts_per_feed', $storeId) ?: 25;
    }

    public function isFeedFullContent(?int $storeId = null): bool
    {
        return (bool) $this->getValue('feeds/include_full_content', $storeId);
    }

    public function isPerCategoryFeeds(?int $storeId = null): bool
    {
        return (bool) $this->getValue('feeds/per_category_feeds', $storeId);
    }

    public function isPerTagFeeds(?int $storeId = null): bool
    {
        return (bool) $this->getValue('feeds/per_tag_feeds', $storeId);
    }

    public function isPerAuthorFeeds(?int $storeId = null): bool
    {
        return (bool) $this->getValue('feeds/per_author_feeds', $storeId);
    }

    public function getFeedsCacheTtl(?int $storeId = null): int
    {
        return (int) $this->getValue('feeds/cache_ttl_seconds', $storeId) ?: 3600;
    }

    public function getTitleTemplate(?int $storeId = null): string
    {
        return (string) $this->getValue('seo/title_template', $storeId);
    }

    public function getDefaultMetaDescription(?int $storeId = null): string
    {
        return (string) $this->getValue('seo/default_meta_description', $storeId);
    }

    public function getTagThinThreshold(?int $storeId = null): int
    {
        return (int) $this->getValue('seo/tag_thin_content_threshold', $storeId) ?: 3;
    }

    public function isOgAutoGenerate(?int $storeId = null): bool
    {
        return (bool) $this->getValue('seo/og_auto_generate', $storeId);
    }

    public function getOgImageBrandColor(?int $storeId = null): string
    {
        $value = (string) $this->getValue('seo/og_image_brand_color', $storeId);
        return $value !== '' ? $value : '#f97316';
    }

    public function isAutoExtractTldr(?int $storeId = null): bool
    {
        return (bool) $this->getValue('aeo/auto_extract_tldr', $storeId);
    }

    public function isHowtoAutoDetect(?int $storeId = null): bool
    {
        return (bool) $this->getValue('aeo/howto_auto_detect', $storeId);
    }

    public function getCitationStyle(?int $storeId = null): string
    {
        return (string) $this->getValue('aeo/citation_style', $storeId);
    }

    public function isLlmsTxtIncludeEnabled(?int $storeId = null): bool
    {
        return (bool) $this->getValue('aio/llms_txt_include', $storeId);
    }

    public function getLlmsTxtMaxPosts(?int $storeId = null): int
    {
        return (int) $this->getValue('aio/llms_txt_max_posts', $storeId);
    }

    public function isLlmsFullTxtIncludeEnabled(?int $storeId = null): bool
    {
        return (bool) $this->getValue('aio/llms_full_txt_include', $storeId);
    }

    public function isMarkdownExportEnabled(?int $storeId = null): bool
    {
        return (bool) $this->getValue('aio/markdown_export_enabled', $storeId);
    }

    public function isIndexNowEnabled(?int $storeId = null): bool
    {
        return (bool) $this->getValue('indexnow/enabled', $storeId);
    }

    public function isNotifyOnPublish(?int $storeId = null): bool
    {
        return (bool) $this->getValue('indexnow/notify_on_publish', $storeId);
    }

    public function isNotifyOnUpdate(?int $storeId = null): bool
    {
        return (bool) $this->getValue('indexnow/notify_on_update', $storeId);
    }

    public function isNotifyOnDelete(?int $storeId = null): bool
    {
        return (bool) $this->getValue('indexnow/notify_on_delete', $storeId);
    }

    public function getIndexNowBatchSize(?int $storeId = null): int
    {
        return (int) $this->getValue('indexnow/batch_size', $storeId) ?: 100;
    }

    public function isCommentsEnabled(?int $storeId = null): bool
    {
        return (bool) $this->getValue('comments/enabled', $storeId);
    }

    public function getCommentsAllowFor(?int $storeId = null): string
    {
        $value = (string) $this->getValue('comments/allow_for', $storeId);
        return $value !== '' ? $value : 'everyone';
    }

    public function isCommentsAutoApproveRegistered(?int $storeId = null): bool
    {
        return (bool) $this->getValue('comments/auto_approve_registered', $storeId);
    }

    public function isCommentsThreadingEnabled(?int $storeId = null): bool
    {
        return (bool) $this->getValue('comments/enable_threading', $storeId);
    }

    public function isCommentsNofollowExternal(?int $storeId = null): bool
    {
        return (bool) $this->getValue('comments/nofollow_external_links', $storeId);
    }

    public function getCommentsMaxPerHourPerIp(?int $storeId = null): int
    {
        return max(0, (int) $this->getValue('comments/max_per_hour_per_ip', $storeId));
    }

    public function isLazyLoadImages(?int $storeId = null): bool
    {
        return (bool) $this->getValue('perf/lazy_load_images', $storeId);
    }

    public function isWebpEnabled(?int $storeId = null): bool
    {
        return (bool) $this->getValue('perf/webp_enabled', $storeId);
    }

    public function getResponsiveSizes(?int $storeId = null): array
    {
        $raw = (string) $this->getValue('perf/responsive_image_sizes', $storeId);
        if ($raw === '') {
            return [480, 800, 1200];
        }
        $parts = array_map('trim', explode(',', $raw));
        $ints = [];
        foreach ($parts as $part) {
            if ($part === '' || !ctype_digit($part)) {
                continue;
            }
            $ints[] = (int) $part;
        }
        return $ints !== [] ? $ints : [480, 800, 1200];
    }

    public function getPerfCacheTtl(?int $storeId = null): int
    {
        return (int) $this->getValue('perf/cache_ttl_seconds', $storeId) ?: 7200;
    }

    public function getUrlHistoryRetentionDays(?int $storeId = null): int
    {
        return (int) $this->getValue('advanced/url_history_retention_days', $storeId) ?: 365;
    }

    public function isDebugLogEnabled(?int $storeId = null): bool
    {
        return (bool) $this->getValue('advanced/debug_log_enabled', $storeId);
    }

    public function isShowBadges(?int $storeId = null): bool
    {
        return (bool) $this->getValue('display/show_badges', $storeId);
    }

    public function isShowFeaturedBadge(?int $storeId = null): bool
    {
        return (bool) $this->getValue('display/show_featured_badge', $storeId);
    }

    public function getNewBadgeDays(?int $storeId = null): int
    {
        return (int) $this->getValue('display/new_badge_days', $storeId);
    }

    public function isOgUseFeaturedImage(?int $storeId = null): bool
    {
        return (bool) $this->getValue('seo/og_use_featured_image', $storeId);
    }

    public function isCommentNotifyAdminEnabled(?int $storeId = null): bool
    {
        return (bool) $this->getValue('comments/notify_admin_enabled', $storeId);
    }

    public function getCommentNotifyRecipient(?int $storeId = null): string
    {
        $configured = trim((string) $this->getValue('comments/notify_recipient_email', $storeId));
        if ($configured !== '') {
            return $configured;
        }

        return trim((string) $this->scopeConfig->getValue(
            'trans_email/ident_general/email',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    public function getCommentNotifySenderIdentity(?int $storeId = null): string
    {
        $value = (string) $this->getValue('comments/notify_sender_identity', $storeId);

        return $value !== '' ? $value : 'general';
    }

    public function getValue(string $path, ?int $storeId = null): mixed
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
