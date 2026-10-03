<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Plugin\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\Blog\Api\AuthorRepositoryInterface;
use Panth\Blog\Api\CategoryRepositoryInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Model\Api\PublicReadGuard;
use Panth\Blog\Model\Author;
use Panth\Blog\Model\Category;
use Panth\Blog\Model\Post;
use Panth\Blog\Model\ResourceModel\Category as CategoryResource;
use Panth\Blog\Model\ResourceModel\Post as PostResource;
use Panth\Blog\Plugin\Api\AuthorPublicRead;
use Panth\Blog\Plugin\Api\CategoryPublicRead;
use Panth\Blog\Plugin\Api\PostPublicRead;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class PublicReadPluginsTest extends TestCase
{
    use BlogTestHelpers;

    private function guard(bool $admin, int $storeId = 1): PublicReadGuard
    {
        $guard = $this->getMockBuilder(PublicReadGuard::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['canReadAll', 'getStoreId', 'addRequiredFilters', 'assertFieldsNotUsed'])
            ->getMock();
        $guard->method('canReadAll')->willReturn($admin);
        $guard->method('getStoreId')->willReturn($storeId);
        return $guard;
    }

    public function testAdminListIsUntouched(): void
    {
        $guard = $this->guard(true);
        $guard->expects($this->never())->method('addRequiredFilters');
        $guard->expects($this->never())->method('assertFieldsNotUsed');
        $criteria = $this->createStub(SearchCriteriaInterface::class);

        $this->assertSame([$criteria], (new PostPublicRead($guard, $this->createStub(PostResource::class)))
            ->beforeGetList($this->createStub(PostRepositoryInterface::class), $criteria));
        $this->assertSame([$criteria], (new AuthorPublicRead($guard))
            ->beforeGetList($this->createStub(AuthorRepositoryInterface::class), $criteria));
        $this->assertSame([$criteria], (new CategoryPublicRead($guard, $this->createStub(CategoryResource::class)))
            ->beforeGetList($this->createStub(CategoryRepositoryInterface::class), $criteria));
    }

    public function testPublicPostListIsRestrictedToPublishedInStore(): void
    {
        $guard = $this->guard(false, 3);
        $criteria = $this->createStub(SearchCriteriaInterface::class);
        $guard->expects($this->once())->method('addRequiredFilters')
            ->with($criteria, ['status' => 'published', 'store_id' => '3']);

        (new PostPublicRead($guard, $this->createStub(PostResource::class)))
            ->beforeGetList($this->createStub(PostRepositoryInterface::class), $criteria);
    }

    public function testPublicPostByIdRequiresPublishedAndVisible(): void
    {
        $resource = $this->createStub(PostResource::class);
        $resource->method('getStoreIds')->willReturnOnConsecutiveCalls([0], [2]);
        $plugin = new PostPublicRead($this->guard(false, 1), $resource);
        $repo = $this->createStub(PostRepositoryInterface::class);

        $published = $this->makeModel(Post::class, ['post_id' => 1, 'status' => 'published']);
        $this->assertSame($published, $plugin->afterGetById($repo, $published, 1));

        $this->expectException(NoSuchEntityException::class);
        $plugin->afterGetById($repo, $published, 1);
    }

    public function testPublicPostByIdHidesDrafts(): void
    {
        $resource = $this->createStub(PostResource::class);
        $resource->method('getStoreIds')->willReturn([]);
        $this->expectException(NoSuchEntityException::class);
        (new PostPublicRead($this->guard(false), $resource))->afterGetById(
            $this->createStub(PostRepositoryInterface::class),
            $this->makeModel(Post::class, ['status' => 'draft']),
            4
        );
    }

    public function testAdminSeesDraftById(): void
    {
        $draft = $this->makeModel(Post::class, ['status' => 'draft']);
        $resource = $this->createMock(PostResource::class);
        $resource->expects($this->never())->method('getStoreIds');
        $this->assertSame($draft, (new PostPublicRead($this->guard(true), $resource))
            ->afterGetById($this->createStub(PostRepositoryInterface::class), $draft, 4));
    }

    public function testPublicAuthorListBlocksPrivateFieldsAndInactive(): void
    {
        $guard = $this->guard(false);
        $criteria = $this->createStub(SearchCriteriaInterface::class);
        $guard->expects($this->once())->method('assertFieldsNotUsed')->with($criteria, ['email', 'user_id']);
        $guard->expects($this->once())->method('addRequiredFilters')->with($criteria, ['is_active' => 1]);
        (new AuthorPublicRead($guard))->beforeGetList($this->createStub(AuthorRepositoryInterface::class), $criteria);
    }

    public function testPublicAuthorResultsAreStripped(): void
    {
        $author = $this->makeModel(Author::class, ['email' => 'a@b.c', 'user_id' => 4, 'is_active' => 1]);
        $results = $this->createStub(SearchResultsInterface::class);
        $results->method('getItems')->willReturn([$author]);
        $repo = $this->createStub(AuthorRepositoryInterface::class);

        $plugin = new AuthorPublicRead($this->guard(false));
        $this->assertSame($results, $plugin->afterGetList($repo, $results));
        $this->assertNull($author->getEmail());
        $this->assertNull($author->getUserId());

        $single = $this->makeModel(Author::class, ['email' => 'x@y.z', 'is_active' => 1]);
        $plugin->afterGetById($repo, $single, 1);
        $this->assertNull($single->getEmail());
    }

    public function testAdminAuthorKeepsPrivateData(): void
    {
        $author = $this->makeModel(Author::class, ['email' => 'a@b.c', 'is_active' => 0]);
        $results = $this->createStub(SearchResultsInterface::class);
        $results->method('getItems')->willReturn([$author]);
        $plugin = new AuthorPublicRead($this->guard(true));
        $repo = $this->createStub(AuthorRepositoryInterface::class);

        $plugin->afterGetList($repo, $results);
        $this->assertSame($author, $plugin->afterGetById($repo, $author, 1));
        $this->assertSame('a@b.c', $author->getEmail());
    }

    public function testInactiveAuthorIsHiddenFromPublic(): void
    {
        $this->expectException(NoSuchEntityException::class);
        (new AuthorPublicRead($this->guard(false)))->afterGetById(
            $this->createStub(AuthorRepositoryInterface::class),
            $this->makeModel(Author::class, ['is_active' => 0]),
            2
        );
    }

    public function testCategoryPluginFiltersAndHides(): void
    {
        $guard = $this->guard(false, 5);
        $criteria = $this->createStub(SearchCriteriaInterface::class);
        $guard->expects($this->once())->method('addRequiredFilters')->with($criteria, ['is_active' => 1, 'store_id' => '5']);
        $resource = $this->createStub(CategoryResource::class);
        $resource->method('getStoreIds')->willReturnOnConsecutiveCalls([5], [1]);
        $plugin = new CategoryPublicRead($guard, $resource);
        $repo = $this->createStub(CategoryRepositoryInterface::class);

        $plugin->beforeGetList($repo, $criteria);
        $active = $this->makeModel(Category::class, ['category_id' => 3, 'is_active' => 1]);
        $this->assertSame($active, $plugin->afterGetById($repo, $active, 3));

        $this->expectException(NoSuchEntityException::class);
        $plugin->afterGetById($repo, $active, 3);
    }
}
