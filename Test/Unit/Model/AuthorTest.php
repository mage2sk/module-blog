<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model;

use Panth\Blog\Model\Author;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\TestCase;

class AuthorTest extends TestCase
{
    use BlogTestHelpers;

    public function testIdentities(): void
    {
        $author = $this->makeModel(Author::class);
        $author->setId(5);
        $this->assertSame(['panth_blog_author_5', 'panth_blog_author'], $author->getIdentities());
    }

    public function testJsonFieldsAreDecoded(): void
    {
        $author = $this->makeModel(Author::class, [
            'links' => '{"twitter":"https://x.com/a"}',
            'knows_about' => '["php","magento"]',
            'same_as' => '["https://github.com/a"]',
        ]);

        $this->assertSame(['twitter' => 'https://x.com/a'], $author->getLinksArray());
        $this->assertSame(['php', 'magento'], $author->getKnowsAboutArray());
        $this->assertSame(['https://github.com/a'], $author->getSameAsArray());
    }

    public function testInvalidOrEmptyJsonYieldsEmptyArray(): void
    {
        $author = $this->makeModel(Author::class, [
            'links' => 'not json',
            'knows_about' => '',
            'same_as' => '"scalar"',
        ]);

        $this->assertSame([], $author->getLinksArray());
        $this->assertSame([], $author->getKnowsAboutArray());
        $this->assertSame([], $author->getSameAsArray());
    }

    public function testUserIdCasting(): void
    {
        $this->assertNull($this->makeModel(Author::class, ['user_id' => ''])->getUserId());
        $this->assertSame(9, $this->makeModel(Author::class, ['user_id' => '9'])->getUserId());
        $this->assertNull($this->makeModel(Author::class)->getAuthorId());
    }
}
