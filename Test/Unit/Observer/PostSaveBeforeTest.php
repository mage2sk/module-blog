<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Observer;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Post;
use Panth\Blog\Model\Reader\ReadingTimeCalculator;
use Panth\Blog\Model\Url\SlugGenerator;
use Panth\Blog\Observer\PostSaveBefore;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PostSaveBeforeTest extends TestCase
{
    use BlogTestHelpers;

    private function observer(object $post): Observer
    {
        return new Observer(['event' => new Event(['post' => $post])]);
    }

    private function subject(
        ?PostRepositoryInterface $repository = null,
        bool $tldr = false,
        ?ReadingTimeCalculator $calculator = null,
        ?LoggerInterface $logger = null
    ): PostSaveBefore {
        $config = $this->createStub(Config::class);
        $config->method('isAutoExtractTldr')->willReturn($tldr);
        $config->method('getReadingSpeedWpm')->willReturn(200);

        if ($repository === null) {
            $repository = $this->createStub(PostRepositoryInterface::class);
            $repository->method('getByUrlKey')->willThrowException(new NoSuchEntityException());
        }

        return new PostSaveBefore(
            new SlugGenerator(),
            $repository,
            $config,
            $logger ?? $this->createStub(LoggerInterface::class),
            $calculator
        );
    }

    public function testIgnoresNonPostPayload(): void
    {
        $repository = $this->createMock(PostRepositoryInterface::class);
        $repository->expects($this->never())->method('getByUrlKey');
        $this->subject($repository)->execute($this->observer(new \stdClass()));
    }

    public function testGeneratesUrlKeyFromTitle(): void
    {
        $post = $this->makeModel(Post::class, ['title' => 'The Best Hyva Tips']);
        $this->subject()->execute($this->observer($post));
        $this->assertSame('best-hyva-tips', $post->getUrlKey());
    }

    public function testKeepsExistingUrlKey(): void
    {
        $post = $this->makeModel(Post::class, ['title' => 'Other', 'url_key' => 'custom']);
        $this->subject()->execute($this->observer($post));
        $this->assertSame('custom', $post->getUrlKey());
    }

    public function testSkipsWhenTitleProducesNoSlug(): void
    {
        $post = $this->makeModel(Post::class, ['title' => '!!!']);
        $this->subject()->execute($this->observer($post));
        $this->assertSame('', $post->getUrlKey());
    }

    public function testAppendsSuffixWhenUrlKeyTaken(): void
    {
        $other = $this->makeModel(Post::class, ['post_id' => 50]);
        $repository = $this->createStub(PostRepositoryInterface::class);
        $repository->method('getByUrlKey')->willReturnCallback(function (string $key) use ($other) {
            if (in_array($key, ['hello-world', 'hello-world-2'], true)) {
                return $other;
            }
            throw new NoSuchEntityException();
        });

        $post = $this->makeModel(Post::class, ['title' => 'Hello World', 'post_id' => 7]);
        $this->subject($repository)->execute($this->observer($post));
        $this->assertSame('hello-world-3', $post->getUrlKey());
    }

    public function testOwnUrlKeyIsNotACollision(): void
    {
        $repository = $this->createStub(PostRepositoryInterface::class);
        $repository->method('getByUrlKey')->willReturn($this->makeModel(Post::class, ['post_id' => 7]));

        $post = $this->makeModel(Post::class, ['title' => 'Hello World', 'post_id' => 7]);
        $this->subject($repository)->execute($this->observer($post));
        $this->assertSame('hello-world', $post->getUrlKey());
    }

    public function testComputesReadingMetricsWhenCalculatorPresent(): void
    {
        $post = $this->makeModel(Post::class, ['url_key' => 'x', 'content' => str_repeat('word ', 450)]);
        $this->subject(null, false, new ReadingTimeCalculator())->execute($this->observer($post));
        $this->assertSame(450, $post->getWordCount());
        $this->assertSame(3, $post->getReadingTimeMin());
    }

    public function testNoReadingMetricsWithoutCalculator(): void
    {
        $post = $this->makeModel(Post::class, ['url_key' => 'x', 'content' => 'some words here']);
        $this->subject()->execute($this->observer($post));
        $this->assertNull($post->getWordCount());
    }

    public function testExtractsTldrEndingAtSentenceBoundary(): void
    {
        $sentence = 'This sentence has exactly eight words in it. ';
        $content = '<h2>Heading words should be dropped</h2><p>' . str_repeat($sentence, 20) . '</p>';
        $post = $this->makeModel(Post::class, ['url_key' => 'x', 'content' => $content]);

        $this->subject(null, true)->execute($this->observer($post));

        $summary = (string) $post->getTldrSummary();
        $this->assertStringStartsWith('This sentence', $summary);
        $this->assertStringEndsWith('in it.', $summary);
        $this->assertStringNotContainsString('Heading', $summary);
        $this->assertLessThanOrEqual(120, count(explode(' ', $summary)));
    }

    public function testTldrSkippedForShortContentOrExistingSummary(): void
    {
        $short = $this->makeModel(Post::class, ['url_key' => 'x', 'content' => '<p>Too short.</p>']);
        $this->subject(null, true)->execute($this->observer($short));
        $this->assertNull($short->getTldrSummary());

        $existing = $this->makeModel(Post::class, [
            'url_key' => 'x',
            'content' => str_repeat('word ', 300),
            'tldr_summary' => 'Keep me',
        ]);
        $this->subject(null, true)->execute($this->observer($existing));
        $this->assertSame('Keep me', $existing->getTldrSummary());
    }

    public function testTldrNeedsEnoughWords(): void
    {
        $post = $this->makeModel(Post::class, ['url_key' => 'x', 'content' => str_repeat('extraordinarily ', 40)]);
        $this->subject(null, true)->execute($this->observer($post));
        $this->assertNull($post->getTldrSummary());
    }

    public function testTldrDisabledByConfig(): void
    {
        $post = $this->makeModel(Post::class, ['url_key' => 'x', 'content' => str_repeat('word ', 300)]);
        $this->subject(null, false)->execute($this->observer($post));
        $this->assertNull($post->getTldrSummary());
    }

    public function testFailuresAreLogged(): void
    {
        $post = $this->createStub(Post::class);
        $post->method('getUrlKey')->willThrowException(new \RuntimeException('kaput'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($this->stringContains('kaput'));

        $this->subject(null, false, null, $logger)->execute($this->observer($post));
    }
}
