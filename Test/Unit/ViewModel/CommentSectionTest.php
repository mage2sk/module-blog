<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\ViewModel;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;
use Panth\Blog\Api\CommentRepositoryInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Comment;
use Panth\Blog\Model\Comment\Captcha;
use Panth\Blog\Model\Post;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use Panth\Blog\ViewModel\CommentSection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CommentSectionTest extends TestCase
{
    use BlogTestHelpers;

    private array $filters = [];

    private function section(array $cfg = [], bool $loggedIn = false, ?CommentRepositoryInterface $repo = null, ?LoggerInterface $logger = null): CommentSection
    {
        $cfg += ['isEnabled' => true, 'isCommentsEnabled' => true, 'getCommentsAllowFor' => 'everyone', 'getRouteFrontName' => 'blog', 'isCommentsThreadingEnabled' => true];
        $config = $this->createStub(Config::class);
        foreach ($cfg as $m => $v) {
            $config->method($m)->willReturn($v);
        }

        $builder = $this->createStub(SearchCriteriaBuilder::class);
        $builder->method('addFilter')->willReturnCallback(function ($f, $v) use (&$builder) {
            $this->filters[] = [$f, $v];
            return $builder;
        });
        $builder->method('addSortOrder')->willReturnSelf();
        $builder->method('create')->willReturn($this->createStub(SearchCriteriaInterface::class));
        $sort = $this->createStub(SortOrderBuilder::class);
        $sort->method('setField')->willReturnSelf();
        $sort->method('setDirection')->willReturnSelf();
        $sort->method('create')->willReturn(new SortOrder());

        $session = $this->createStub(CustomerSession::class);
        $session->method('isLoggedIn')->willReturn($loggedIn);
        $formKey = $this->createStub(FormKey::class);
        $formKey->method('getFormKey')->willReturn('fk123');
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(static fn ($p) => 'https://s.test/' . $p);
        $captcha = $this->createStub(Captcha::class);
        $captcha->method('getProvider')->willReturn('math');
        $captcha->method('getSiteKey')->willReturn('site-key');
        $captcha->method('createMathChallenge')->willReturn(['question' => '1 + 1', 'token' => 't']);

        return new CommentSection(
            $config,
            $repo ?? $this->createStub(CommentRepositoryInterface::class),
            $builder,
            $sort,
            $session,
            $formKey,
            $url,
            $logger ?? $this->createStub(LoggerInterface::class),
            $captcha
        );
    }

    public function testVisibility(): void
    {
        $open = $this->makeModel(Post::class, ['enable_comments' => 1]);
        $closed = $this->makeModel(Post::class, ['enable_comments' => 0]);
        $this->assertTrue($this->section()->isVisible($open));
        $this->assertFalse($this->section()->isVisible($closed));
        $this->assertFalse($this->section()->isVisible(null));
        $this->assertFalse($this->section(['isCommentsEnabled' => false])->isVisible($open));
        $this->assertFalse($this->section(['isEnabled' => false])->isVisible($open));
    }

    public static function permissions(): array
    {
        return [
            ['everyone', false, true, ''],
            ['nobody', true, false, 'Comments are closed for this post.'],
            ['registered_only', false, false, 'Sign in to leave a comment.'],
            ['registered_only', true, true, ''],
        ];
    }

    #[DataProvider('permissions')]
    public function testPermissions(string $allow, bool $loggedIn, bool $canPost, string $reason): void
    {
        $section = $this->section(['getCommentsAllowFor' => $allow], $loggedIn);
        $this->assertSame($canPost, $section->canPost());
        $this->assertSame($reason, $section->getReason());
    }

    public function testApprovedCommentsAreFilteredByPostAndStatus(): void
    {
        $comment = $this->makeModel(Comment::class);
        $results = $this->createStub(SearchResultsInterface::class);
        $results->method('getItems')->willReturn([$comment]);
        $repo = $this->createStub(CommentRepositoryInterface::class);
        $repo->method('getList')->willReturn($results);

        $this->assertSame([$comment], $this->section([], false, $repo)->getApproved(9));
        $this->assertSame([['post_id', 9], ['status', 'approved']], $this->filters);
        $this->assertSame([], $this->section([], false, $repo)->getApproved(0));
    }

    public function testApprovedCommentsFailureIsLogged(): void
    {
        $repo = $this->createStub(CommentRepositoryInterface::class);
        $repo->method('getList')->willThrowException(new \RuntimeException('down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($this->stringContains('down'));
        $this->assertSame([], $this->section([], false, $repo, $logger)->getApproved(3));
    }

    public function testFormHelpers(): void
    {
        $section = $this->section();
        $this->assertSame('fk123', $section->getFormKey());
        $this->assertSame('https://s.test/blog/comment/submit', $section->getSubmitUrl());
        $this->assertTrue($section->isThreadingEnabled());
        $this->assertSame('math', $section->getCaptchaProvider());
        $this->assertSame('site-key', $section->getCaptchaSiteKey());
        $this->assertSame('1 + 1', $section->getMathChallenge()['question']);
    }

    public function testThreadedCommentsNestRepliesUnderApprovedParents(): void
    {
        $root = $this->makeModel(Comment::class, ['comment_id' => 1, 'parent_id' => null]);
        $reply = $this->makeModel(Comment::class, ['comment_id' => 2, 'parent_id' => 1]);
        $deep = $this->makeModel(Comment::class, ['comment_id' => 3, 'parent_id' => 2]);
        $orphan = $this->makeModel(Comment::class, ['comment_id' => 4, 'parent_id' => 99]);

        $tree = $this->section()->getThreaded([$root, $reply, $deep, $orphan]);
        $this->assertCount(2, $tree);
        $this->assertSame($root, $tree[0]['comment']);
        $this->assertSame($reply, $tree[0]['replies'][0]['comment']);
        $this->assertSame($deep, $tree[0]['replies'][0]['replies'][0]['comment']);
        $this->assertSame($orphan, $tree[1]['comment']);
        $this->assertSame([], $tree[1]['replies']);
    }

    public function testThreadingDisabledKeepsAFlatList(): void
    {
        $root = $this->makeModel(Comment::class, ['comment_id' => 1]);
        $reply = $this->makeModel(Comment::class, ['comment_id' => 2, 'parent_id' => 1]);
        $tree = $this->section(['isCommentsThreadingEnabled' => false])->getThreaded([$root, $reply]);
        $this->assertCount(2, $tree);
        $this->assertSame([], $tree[0]['replies']);
    }
}
