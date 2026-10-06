<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Observer;

use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Category;
use Panth\Blog\Model\Comment;
use Panth\Blog\Model\Post;
use Panth\Blog\Observer\CategorySaveAfter;
use Panth\Blog\Model\Url\UrlHistoryManager;
use Panth\Blog\Observer\CommentNotification;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CommentNotificationTest extends TestCase
{
    use BlogTestHelpers;

    private array $vars = [];

    private function config(bool $enabled = true, string $recipient = 'mod@example.com'): Config
    {
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn($enabled);
        $config->method('isCommentsEnabled')->willReturn(true);
        $config->method('isCommentNotifyAdminEnabled')->willReturn(true);
        $config->method('getCommentNotifyRecipient')->willReturn($recipient);
        $config->method('getCommentNotifySenderIdentity')->willReturn('general');
        $config->method('getRouteFrontName')->willReturn('blog');
        return $config;
    }

    private function transport(bool $expectSend): TransportBuilder
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($expectSend ? $this->once() : $this->never())->method('sendMessage');

        $builder = $this->createStub(TransportBuilder::class);
        $builder->method('setTemplateIdentifier')->willReturnSelf();
        $builder->method('setTemplateOptions')->willReturnSelf();
        $builder->method('setTemplateVars')->willReturnCallback(function (array $vars) use ($builder) {
            $this->vars = $vars;
            return $builder;
        });
        $builder->method('setFromByScope')->willReturnSelf();
        $builder->method('addTo')->willReturnSelf();
        $builder->method('getTransport')->willReturn($transport);
        return $builder;
    }

    private function subject(Config $config, TransportBuilder $builder, ?PostRepositoryInterface $posts = null): CommentNotification
    {
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $front = $this->createStub(UrlInterface::class);
        $front->method('getUrl')->willReturnCallback(static fn ($p) => 'https://s.test/' . $p);
        $backend = $this->createStub(BackendUrl::class);
        $backend->method('getUrl')->willReturn('https://s.test/admin/comments');

        if ($posts === null) {
            $post = $this->makeModel(Post::class, ['title' => 'Great Post', 'url_key' => 'great-post']);
            $posts = $this->createStub(PostRepositoryInterface::class);
            $posts->method('getById')->willReturn($post);
        }

        return new CommentNotification($config, $storeManager, $builder, $front, $backend, $posts, $this->createStub(LoggerInterface::class));
    }

    private function observer(mixed $comment, string $key = 'object'): Observer
    {
        return new Observer(['event' => new Event([$key => $comment])]);
    }

    private function comment(array $orig = []): Comment
    {
        $comment = $this->makeModel(Comment::class, [
            'post_id' => 3,
            'author_name' => 'Ann',
            'author_email' => 'ann@example.com',
            'content' => 'Nice!',
            'status' => 'pending',
        ]);
        foreach ($orig as $k => $v) {
            $comment->setOrigData($k, $v);
        }
        return $comment;
    }

    public function testSendsNotificationForNewComment(): void
    {
        $this->subject($this->config(), $this->transport(true))->execute($this->observer($this->comment(), 'comment'));

        $this->assertSame('Great Post', $this->vars['post_title']);
        $this->assertSame('https://s.test/blog/great-post', $this->vars['post_url']);
        $this->assertSame('Ann', $this->vars['author_name']);
        $this->assertSame('pending', $this->vars['status']);
        $this->assertSame('https://s.test/admin/comments', $this->vars['moderation_url']);
    }

    public function testExistingCommentIsNotNotified(): void
    {
        $this->subject($this->config(), $this->transport(false))->execute($this->observer($this->comment(['comment_id' => 9])));
    }

    public function testDisabledOrNoRecipientSendsNothing(): void
    {
        $this->subject($this->config(false), $this->transport(false))->execute($this->observer($this->comment()));
        $this->subject($this->config(true, ''), $this->transport(false))->execute($this->observer($this->comment()));
    }

    public function testNonCommentPayloadIgnored(): void
    {
        $this->subject($this->config(), $this->transport(false))->execute($this->observer(new \stdClass()));
    }

    public function testMissingPostFallsBackToUntitled(): void
    {
        $posts = $this->createStub(PostRepositoryInterface::class);
        $posts->method('getById')->willThrowException(new NoSuchEntityException());
        $this->subject($this->config(), $this->transport(true), $posts)->execute($this->observer($this->comment()));

        $this->assertSame('Untitled post', $this->vars['post_title']);
        $this->assertSame('', $this->vars['post_url']);
    }

    public function testCategoryRenameIsRecorded(): void
    {
        $history = $this->createMock(UrlHistoryManager::class);
        $history->expects($this->once())->method('recordSlugChange')->with('category', 4, 'old', 'new');

        $category = $this->makeModel(Category::class, ['category_id' => 4, 'url_key' => 'new']);
        $category->setOrigData('url_key', 'old');
        (new CategorySaveAfter($history, $this->createStub(LoggerInterface::class)))
            ->execute(new Observer(['event' => new Event(['category' => $category])]));
    }

    public function testCategoryWithoutRenameIsIgnored(): void
    {
        $history = $this->createMock(UrlHistoryManager::class);
        $history->expects($this->never())->method('recordSlugChange');
        $observer = new CategorySaveAfter($history, $this->createStub(LoggerInterface::class));

        $category = $this->makeModel(Category::class, ['category_id' => 4, 'url_key' => 'same']);
        $category->setOrigData('url_key', 'same');
        $observer->execute(new Observer(['event' => new Event(['category' => $category])]));

        $fresh = $this->makeModel(Category::class, ['category_id' => 5, 'url_key' => 'new']);
        $observer->execute(new Observer(['event' => new Event(['category' => $fresh])]));
        $observer->execute(new Observer(['event' => new Event(['category' => 'nope'])]));
    }
}
