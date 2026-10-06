<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Controller\Comment;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Panth\Blog\Api\CommentRepositoryInterface;
use Panth\Blog\Api\Data\CommentInterfaceFactory;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Controller\Comment\Submit;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Comment;
use Panth\Blog\Model\Comment\Captcha;
use Panth\Blog\Model\Post;
use Panth\Blog\Model\ResourceModel\Comment as CommentResource;
use Panth\Blog\Model\StoreVisibility;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\TestCase;

class SubmitTest extends TestCase
{
    use BlogTestHelpers;

    private array $cfg = [];
    private array $params = [];
    private array $errors = [];
    private array $successes = [];
    private ?string $redirectUrl = null;
    private ?string $redirectPath = null;
    private bool $loggedIn = false;
    private bool $formKeyValid = true;
    private bool $captchaValid = true;
    private bool $visible = true;
    private int $recent = 0;
    private ?Post $post = null;
    private ?Comment $saved = null;
    private ?CommentRepositoryInterface $commentRepository = null;

    protected function setUp(): void
    {
        $this->cfg = [
            'isEnabled' => true,
            'isCommentsEnabled' => true,
            'getCommentsAllowFor' => 'everyone',
            'getCommentsMaxPerHourPerIp' => 0,
            'isCommentsThreadingEnabled' => true,
            'isCommentsNofollowExternal' => false,
            'isCommentsAutoApproveRegistered' => false,
            'getRouteFrontName' => 'blog',
        ];
        $this->params = [
            'post_slug' => 'hello',
            'author_name' => ' Ann ',
            'author_email' => 'ann@example.com',
            'content' => ' Great post ',
        ];
        $this->post = $this->makeModel(Post::class, ['post_id' => 7, 'status' => 'published', 'enable_comments' => 1]);
    }

    private function execute(): void
    {
        $config = $this->createStub(Config::class);
        foreach ($this->cfg as $method => $value) {
            $config->method($method)->willReturn($value);
        }

        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnCallback(fn ($k, $d = null) => $this->params[$k] ?? $d);
        $request->method('getClientIp')->willReturn('10.0.0.1');
        $request->method('getServer')->willReturn('UnitAgent');

        $redirect = $this->createStub(Redirect::class);
        $redirect->method('setUrl')->willReturnCallback(function ($url) use ($redirect) {
            $this->redirectUrl = $url;
            return $redirect;
        });
        $redirect->method('setPath')->willReturnCallback(function ($path) use ($redirect) {
            $this->redirectPath = $path;
            return $redirect;
        });
        $resultFactory = $this->createStub(ResultFactory::class);
        $resultFactory->method('create')->willReturn($redirect);

        $formKey = $this->createStub(FormKeyValidator::class);
        $formKey->method('validate')->willReturn($this->formKeyValid);

        $posts = $this->createStub(PostRepositoryInterface::class);
        $posts->method('getByUrlKey')->willReturnCallback(function () {
            if ($this->post === null) {
                throw new NoSuchEntityException();
            }
            return $this->post;
        });

        if ($this->commentRepository === null) {
            $this->commentRepository = $this->createStub(CommentRepositoryInterface::class);
            $this->commentRepository->method('save')->willReturnCallback(function ($c) {
                $this->saved = $c;
                return $c;
            });
        }

        $factory = $this->createStub(CommentInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(fn () => $this->makeModel(Comment::class));

        $messages = $this->createStub(ManagerInterface::class);
        $messages->method('addErrorMessage')->willReturnCallback(function ($m) use ($messages) {
            $this->errors[] = (string) $m;
            return $messages;
        });
        $messages->method('addSuccessMessage')->willReturnCallback(function ($m) use ($messages) {
            $this->successes[] = (string) $m;
            return $messages;
        });

        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(static fn ($p) => 'https://s.test/' . $p);

        $session = $this->createStub(CustomerSession::class);
        $session->method('isLoggedIn')->willReturn($this->loggedIn);

        $captcha = $this->createStub(Captcha::class);
        $captcha->method('verify')->willReturn($this->captchaValid);

        $visibility = $this->createStub(StoreVisibility::class);
        $visibility->method('isPostVisible')->willReturn($this->visible);

        $resource = $this->createStub(CommentResource::class);
        $resource->method('countRecentByIp')->willReturn($this->recent);

        (new Submit(
            $resultFactory,
            $request,
            $config,
            $formKey,
            $posts,
            $this->commentRepository,
            $factory,
            $messages,
            $url,
            $session,
            $captcha,
            $visibility,
            $resource
        ))->execute();
    }

    public function testValidCommentIsSavedAsPending(): void
    {
        $this->execute();

        $this->assertSame('https://s.test/blog/hello#comments', $this->redirectUrl);
        $this->assertSame(['Thanks, your comment is pending moderation.'], $this->successes);
        $this->assertSame(7, $this->saved->getPostId());
        $this->assertSame('Ann', $this->saved->getAuthorName());
        $this->assertSame('Great post', $this->saved->getContent());
        $this->assertSame('pending', $this->saved->getStatus());
        $this->assertSame('10.0.0.1', $this->saved->getIp());
        $this->assertSame('UnitAgent', $this->saved->getUserAgent());
        $this->assertNull($this->saved->getParentId());
    }

    public function testRegisteredUserIsAutoApproved(): void
    {
        $this->loggedIn = true;
        $this->cfg['isCommentsAutoApproveRegistered'] = true;
        $this->execute();
        $this->assertSame('approved', $this->saved->getStatus());
        $this->assertSame(['Thanks, your comment has been posted.'], $this->successes);
    }

    public function testDisabledModuleGoesToNoRoute(): void
    {
        $this->cfg['isEnabled'] = false;
        $this->execute();
        $this->assertSame('noroute', $this->redirectPath);
        $this->assertNull($this->saved);
    }

    public function testCommentsDisabledRedirectsSilently(): void
    {
        $this->cfg['isCommentsEnabled'] = false;
        $this->params['post_slug'] = '';
        $this->execute();
        $this->assertSame('https://s.test/blog', $this->redirectUrl);
        $this->assertSame([], $this->errors);
    }

    public static function rejections(): array
    {
        return [
            'nobody allowed' => [['cfg' => ['getCommentsAllowFor' => 'nobody']], 'Comment submission is not permitted.'],
            'guest on registered only' => [['cfg' => ['getCommentsAllowFor' => 'registered_only']], 'Comment submission is not permitted.'],
            'bad form key' => [['formKeyValid' => false], 'Invalid form key. Please try again.'],
            'captcha' => [['captchaValid' => false], 'Please complete the verification check and try again.'],
            'missing slug' => [['params' => ['post_slug' => '']], 'Missing post reference.'],
            'unknown post' => [['post' => null], 'Post not found.'],
            'invisible post' => [['visible' => false], 'Comments are closed for this post.'],
            'missing name' => [['params' => ['author_name' => '  ']], 'Please fill in your name and comment.'],
            'missing content' => [['params' => ['content' => '']], 'Please fill in your name and comment.'],
            'bad email' => [['params' => ['author_email' => 'nope']], 'Please provide a valid email address.'],
            'rate limited' => [['cfg' => ['getCommentsMaxPerHourPerIp' => 3], 'recent' => 3], 'Too many comments from your address. Please try again later.'],
        ];
    }

    public function testRegisteredOnlyAllowsLoggedInCustomers(): void
    {
        $this->cfg['getCommentsAllowFor'] = 'registered_only';
        $this->loggedIn = true;
        $this->execute();
        $this->assertNotNull($this->saved);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rejections')]
    public function testRejections(array $overrides, string $message): void
    {
        foreach ($overrides['cfg'] ?? [] as $k => $v) {
            $this->cfg[$k] = $v;
        }
        foreach ($overrides['params'] ?? [] as $k => $v) {
            $this->params[$k] = $v;
        }
        foreach (['formKeyValid', 'captchaValid', 'visible', 'recent'] as $prop) {
            if (array_key_exists($prop, $overrides)) {
                $this->{$prop} = $overrides[$prop];
            }
        }
        if (array_key_exists('post', $overrides)) {
            $this->post = null;
        }

        $this->execute();
        $this->assertSame([$message], $this->errors);
        $this->assertNull($this->saved);
        $this->assertNotNull($this->redirectUrl);
    }

    public function testClosedPostsRejectComments(): void
    {
        $this->post = $this->makeModel(Post::class, ['post_id' => 7, 'status' => 'published', 'enable_comments' => 0]);
        $this->execute();
        $this->assertSame(['Comments are closed for this post.'], $this->errors);

        $this->errors = [];
        $this->post = $this->makeModel(Post::class, ['post_id' => 7, 'status' => 'draft', 'enable_comments' => 1]);
        $this->execute();
        $this->assertSame(['Comments are closed for this post.'], $this->errors);
    }

    public function testReplyKeepsParentOfSamePostOnly(): void
    {
        $this->params['parent_id'] = '3';
        $this->commentRepository = $this->createStub(CommentRepositoryInterface::class);
        $this->commentRepository->method('getById')->willReturnCallback(fn (int $id) => $this->makeModel(Comment::class, ['post_id' => $id === 3 ? 7 : 99]));
        $this->commentRepository->method('save')->willReturnCallback(function ($c) {
            $this->saved = $c;
            return $c;
        });
        $this->execute();
        $this->assertSame(3, $this->saved->getParentId());

        $this->params['parent_id'] = '4';
        $this->execute();
        $this->assertNull($this->saved->getParentId());
    }

    public function testThreadingDisabledDropsParent(): void
    {
        $this->cfg['isCommentsThreadingEnabled'] = false;
        $this->params['parent_id'] = '3';
        $this->execute();
        $this->assertNull($this->saved->getParentId());
    }

    public function testExternalLinksGetNofollow(): void
    {
        $this->cfg['isCommentsNofollowExternal'] = true;
        $this->params['content'] = 'See <a href="https://ext.test/x">this</a> and <a class="c" href=\'http://b.test\' rel="ugc">that</a> and <a href="/local">here</a>';
        $this->execute();

        $content = $this->saved->getContent();
        $this->assertStringContainsString('<a href="https://ext.test/x" rel="nofollow">', $content);
        $this->assertStringContainsString('rel="ugc nofollow"', $content);
        $this->assertStringContainsString('<a href="/local">', $content);
    }

    public function testSaveFailureShowsError(): void
    {
        $this->commentRepository = $this->createStub(CommentRepositoryInterface::class);
        $this->commentRepository->method('save')->willThrowException(new \RuntimeException('db'));
        $this->execute();
        $this->assertSame(['Unable to submit comment.'], $this->errors);
        $this->assertSame([], $this->successes);
    }

    public function testOptionalFieldsBecomeNull(): void
    {
        $this->params['author_email'] = '';
        $this->params['author_website'] = '';
        $this->execute();
        $this->assertNull($this->saved->getAuthorEmail());
        $this->assertNull($this->saved->getAuthorWebsite());
    }

    public function testHoneypotSubmissionLooksAcceptedButIsNotSaved(): void
    {
        $this->params['pb_website_url'] = 'http://spam.example.com';
        $this->execute();
        $this->assertNull($this->saved);
        $this->assertSame(['Thanks, your comment is pending moderation.'], $this->successes);
        $this->assertSame('https://s.test/blog/hello#comments', $this->redirectUrl);
    }

    public function testRejectedSubmissionReturnsToTheForm(): void
    {
        $this->cfg['getCommentsMaxPerHourPerIp'] = 2;
        $this->recent = 2;
        $this->execute();
        $this->assertNull($this->saved);
        $this->assertSame('https://s.test/blog/hello#pb-comment-form', $this->redirectUrl);

        $this->recent = 0;
        $this->params['content'] = '  ';
        $this->execute();
        $this->assertSame('https://s.test/blog/hello#pb-comment-form', $this->redirectUrl);
    }
}
