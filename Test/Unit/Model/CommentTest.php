<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model;

use Panth\Blog\Model\Comment;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\TestCase;

class CommentTest extends TestCase
{
    use BlogTestHelpers;

    public function testDefaultStatusIsPending(): void
    {
        $comment = $this->makeModel(Comment::class);
        $this->assertSame(Comment::STATUS_PENDING, $comment->getStatus());
        $this->assertTrue($comment->isPending());
        $this->assertFalse($comment->isApproved());
    }

    public function testApprovedHelper(): void
    {
        $comment = $this->makeModel(Comment::class, ['status' => 'approved']);
        $this->assertTrue($comment->isApproved());
        $this->assertFalse($comment->isPending());
    }

    public function testBeforeSaveStampsApprovedAtForApprovedComments(): void
    {
        $comment = $this->makeModel(Comment::class, ['status' => 'approved']);
        $before = gmdate('Y-m-d H:i:s');
        $comment->beforeSave();

        $stamp = (string) $comment->getData('approved_at');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $stamp);
        $this->assertGreaterThanOrEqual($before, $stamp);
    }

    public function testBeforeSaveKeepsExistingApprovedAt(): void
    {
        $comment = $this->makeModel(Comment::class, ['status' => 'approved', 'approved_at' => '2020-01-01 00:00:00']);
        $comment->beforeSave();
        $this->assertSame('2020-01-01 00:00:00', $comment->getData('approved_at'));
    }

    public function testBeforeSaveDoesNotStampPendingComments(): void
    {
        $comment = $this->makeModel(Comment::class, ['status' => 'pending']);
        $comment->beforeSave();
        $this->assertNull($comment->getData('approved_at'));
    }

    public function testIdentitiesAndCasting(): void
    {
        $comment = $this->makeModel(Comment::class, ['post_id' => '12', 'parent_id' => '']);
        $comment->setId(3);
        $this->assertSame(['panth_blog_comment_3', 'panth_blog_comment'], $comment->getIdentities());
        $this->assertSame(12, $comment->getPostId());
        $this->assertNull($comment->getParentId());
    }
}
