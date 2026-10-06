<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model;

use Panth\Blog\Api\Data\PostInterface;
use Panth\Blog\Model\Post;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PostTest extends TestCase
{
    use BlogTestHelpers;

    private function post(array $data = []): Post
    {
        return $this->makeModel(Post::class, $data);
    }

    public function testIdentitiesIncludeIdAndGenericTag(): void
    {
        $post = $this->post();
        $post->setId(42);
        $this->assertSame(['panth_blog_post_42', 'panth_blog_post'], $post->getIdentities());
    }

    public static function statusProvider(): array
    {
        return [
            [PostInterface::STATUS_PUBLISHED, true, false, false, false],
            [PostInterface::STATUS_DRAFT, false, true, false, false],
            [PostInterface::STATUS_SCHEDULED, false, false, true, false],
            [PostInterface::STATUS_ARCHIVED, false, false, false, true],
        ];
    }

    #[DataProvider('statusProvider')]
    public function testStatusHelpers(string $status, bool $published, bool $draft, bool $scheduled, bool $archived): void
    {
        $post = $this->post(['status' => $status]);
        $this->assertSame($published, $post->hasPublished());
        $this->assertSame($draft, $post->isDraft());
        $this->assertSame($scheduled, $post->isScheduled());
        $this->assertSame($archived, $post->isArchived());
    }

    public function testDefaultsForUnsetFields(): void
    {
        $post = $this->post();
        $this->assertSame(PostInterface::STATUS_DRAFT, $post->getStatus());
        $this->assertTrue($post->isDraft());
        $this->assertSame('index,follow', $post->getMetaRobots());
        $this->assertSame('default', $post->getLayoutTemplate());
        $this->assertNull($post->getPostId());
        $this->assertNull($post->getAuthorId());
        $this->assertNull($post->getReadingTimeMin());
        $this->assertNull($post->getWordCount());
        $this->assertNull($post->getContent());
        $this->assertSame('', $post->getTitle());
        $this->assertSame(0, $post->getViewCount());
    }

    public function testEmptyStringNumericFieldsBecomeNull(): void
    {
        $post = $this->post(['author_id' => '', 'reading_time_min' => '', 'word_count' => '']);
        $this->assertNull($post->getAuthorId());
        $this->assertNull($post->getReadingTimeMin());
        $this->assertNull($post->getWordCount());
    }

    public function testNumericStringsAreCast(): void
    {
        $post = $this->post(['post_id' => '7', 'author_id' => '3', 'is_featured' => '1', 'word_count' => '120', 'sort_order' => '5']);
        $this->assertSame(7, $post->getPostId());
        $this->assertSame(3, $post->getAuthorId());
        $this->assertSame(1, $post->getIsFeatured());
        $this->assertSame(120, $post->getWordCount());
        $this->assertSame(5, $post->getSortOrder());
    }

    public function testSettersAreFluentAndRoundTrip(): void
    {
        $post = $this->post();
        $this->assertSame($post, $post->setTitle('T')->setUrlKey('t')->setStatus('published')->setAuthorId(null));
        $this->assertSame('T', $post->getTitle());
        $this->assertSame('t', $post->getUrlKey());
        $this->assertTrue($post->hasPublished());
        $this->assertNull($post->getAuthorId());
    }
}
