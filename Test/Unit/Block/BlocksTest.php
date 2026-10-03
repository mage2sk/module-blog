<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Block;

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Context as BlockContext;
use Magento\Framework\View\Element\Template\Context as TemplateContext;
use Panth\Blog\Block\CacheTags;
use Panth\Blog\Block\Post\FaqWidget;
use Panth\Blog\Model\Post;
use Panth\Blog\Model\ResourceModel\Post as PostResource;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\TestCase;

class BlocksTest extends TestCase
{
    use BlogTestHelpers;

    public function testCacheTagsExposeAllEntityTags(): void
    {
        $block = new CacheTags($this->createStub(BlockContext::class));
        $this->assertSame(
            ['panth_blog_post', 'panth_blog_category', 'panth_blog_tag', 'panth_blog_author', 'panth_blog_comment'],
            $block->getIdentities()
        );
    }

    private function faqWidget(mixed $current, ?PostResource $resource = null): FaqWidget
    {
        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturn($current);
        return new FaqWidget(
            $this->createStub(TemplateContext::class),
            $registry,
            $resource ?? $this->createStub(PostResource::class)
        );
    }

    public function testFaqWidgetIsEmptyWithoutCurrentPost(): void
    {
        $resource = $this->createMock(PostResource::class);
        $resource->expects($this->never())->method('getPrimaryCategoryId');
        $this->assertSame('', $this->faqWidget(null, $resource)->getFaqHtml());
        $this->assertSame('', $this->faqWidget(new \stdClass(), $resource)->getFaqHtml());
    }

    public function testFaqWidgetIsEmptyWithoutPrimaryCategory(): void
    {
        $resource = $this->createStub(PostResource::class);
        $resource->method('getPrimaryCategoryId')->willReturn(null);
        $post = $this->makeModel(Post::class, ['post_id' => 4]);
        $this->assertSame('', $this->faqWidget($post, $resource)->getFaqHtml());
        $this->assertSame('', $this->faqWidget($this->makeModel(Post::class), $resource)->getFaqHtml());
    }
}
