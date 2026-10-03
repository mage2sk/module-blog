<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model\Resolver;

use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Panth\Blog\Api\AuthorRepositoryInterface;
use Panth\Blog\Api\CategoryRepositoryInterface;
use Panth\Blog\Api\PostRepositoryInterface;
use Panth\Blog\Api\TagRepositoryInterface;
use Panth\Blog\Model\Author;
use Panth\Blog\Model\Category;
use Panth\Blog\Model\Post;
use Panth\Blog\Model\Resolver\AuthorsResolver;
use Panth\Blog\Model\Resolver\CategoriesResolver;
use Panth\Blog\Model\Resolver\PostResolver;
use Panth\Blog\Model\Resolver\PostsResolver;
use Panth\Blog\Model\Resolver\TagsResolver;
use Panth\Blog\Model\StoreVisibility;
use Panth\Blog\Model\Tag;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\TestCase;

class ResolversTest extends TestCase
{
    use BlogTestHelpers;

    private array $filters = [];

    private function field(): Field
    {
        return $this->createStub(Field::class);
    }

    private function info(): ResolveInfo
    {
        return $this->createStub(ResolveInfo::class);
    }

    private function criteriaBuilder(): SearchCriteriaBuilder
    {
        $this->filters = [];
        $builder = $this->createStub(SearchCriteriaBuilder::class);
        $builder->method('addFilter')->willReturnCallback(function ($field, $value, $type = 'eq') use (&$builder) {
            $this->filters[] = [$field, $value, $type];
            return $builder;
        });
        $builder->method('setPageSize')->willReturnSelf();
        $builder->method('setCurrentPage')->willReturnSelf();
        $builder->method('addSortOrder')->willReturnSelf();
        $builder->method('create')->willReturn($this->createStub(SearchCriteriaInterface::class));
        return $builder;
    }

    private function results(array $items, int $total = 0): SearchResultsInterface
    {
        $results = $this->createStub(SearchResultsInterface::class);
        $results->method('getItems')->willReturn($items);
        $results->method('getTotalCount')->willReturn($total);
        return $results;
    }

    private function visibility(int $store = 2, bool $visible = true): StoreVisibility
    {
        $visibility = $this->createStub(StoreVisibility::class);
        $visibility->method('getCurrentStoreId')->willReturn($store);
        $visibility->method('isPostVisible')->willReturn($visible);
        $visibility->method('filterCategories')->willReturnArgument(0);
        return $visibility;
    }

    public function testAuthorsResolverReturnsActiveAuthorData(): void
    {
        $repo = $this->createStub(AuthorRepositoryInterface::class);
        $repo->method('getList')->willReturn($this->results([$this->makeModel(Author::class, ['display_name' => 'Jane'])]));
        $out = (new AuthorsResolver($repo, $this->criteriaBuilder()))->resolve($this->field(), null, $this->info());

        $this->assertSame([['display_name' => 'Jane']], $out);
        $this->assertSame([['is_active', 1, 'eq']], $this->filters);
    }

    public function testCategoriesResolverFiltersByStore(): void
    {
        $repo = $this->createStub(CategoryRepositoryInterface::class);
        $repo->method('getList')->willReturn($this->results([$this->makeModel(Category::class, ['name' => 'News'])]));
        $out = (new CategoriesResolver($repo, $this->criteriaBuilder(), $this->visibility(4)))->resolve($this->field(), null, $this->info());

        $this->assertSame([['name' => 'News']], $out);
        $this->assertSame([['is_active', 1, 'eq'], ['store_id', '4', 'eq']], $this->filters);
    }

    public function testListResolversSwallowFailures(): void
    {
        $authors = $this->createStub(AuthorRepositoryInterface::class);
        $authors->method('getList')->willThrowException(new \RuntimeException('x'));
        $tags = $this->createStub(TagRepositoryInterface::class);
        $tags->method('getList')->willThrowException(new \RuntimeException('x'));
        $categories = $this->createStub(CategoryRepositoryInterface::class);
        $categories->method('getList')->willThrowException(new \RuntimeException('x'));

        $this->assertSame([], (new AuthorsResolver($authors, $this->criteriaBuilder()))->resolve($this->field(), null, $this->info()));
        $this->assertSame([], (new TagsResolver($tags, $this->criteriaBuilder()))->resolve($this->field(), null, $this->info()));
        $this->assertSame([], (new CategoriesResolver($categories, $this->criteriaBuilder(), $this->visibility()))->resolve($this->field(), null, $this->info()));
    }

    public function testTagsResolverReturnsAllTags(): void
    {
        $repo = $this->createStub(TagRepositoryInterface::class);
        $repo->method('getList')->willReturn($this->results([$this->makeModel(Tag::class, ['name' => 'php']), new \stdClass()]));
        $this->assertSame([['name' => 'php']], (new TagsResolver($repo, $this->criteriaBuilder()))->resolve($this->field(), null, $this->info()));
    }

    public function testPostResolverById(): void
    {
        $repo = $this->createMock(PostRepositoryInterface::class);
        $repo->expects($this->once())->method('getById')->with(5)
            ->willReturn($this->makeModel(Post::class, ['post_id' => 5, 'status' => 'published']));
        $out = (new PostResolver($repo, $this->visibility()))->resolve($this->field(), null, $this->info(), null, ['id' => '5']);
        $this->assertSame(['post_id' => 5, 'status' => 'published'], $out);
    }

    public function testPostResolverByUrlKeyHidesUnpublishedOrInvisible(): void
    {
        $repo = $this->createStub(PostRepositoryInterface::class);
        $repo->method('getByUrlKey')->willReturnOnConsecutiveCalls(
            $this->makeModel(Post::class, ['status' => 'draft']),
            $this->makeModel(Post::class, ['status' => 'published'])
        );
        $this->assertNull((new PostResolver($repo, $this->visibility()))->resolve($this->field(), null, $this->info(), null, ['urlKey' => 'a']));
        $this->assertNull((new PostResolver($repo, $this->visibility(1, false)))->resolve($this->field(), null, $this->info(), null, ['urlKey' => 'a']));
    }

    public function testPostResolverWithoutArgsOrMissingPost(): void
    {
        $repo = $this->createStub(PostRepositoryInterface::class);
        $repo->method('getById')->willThrowException(new NoSuchEntityException());
        $resolver = new PostResolver($repo, $this->visibility());
        $this->assertNull($resolver->resolve($this->field(), null, $this->info(), null, []));
        $this->assertNull($resolver->resolve($this->field(), null, $this->info(), null, ['id' => 3]));
    }

    private function postsResolver(PostRepositoryInterface $repo, AdapterInterface $connection): PostsResolver
    {
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $sortOrders = $this->createStub(SortOrderBuilder::class);
        $sortOrders->method('setField')->willReturnSelf();
        $sortOrders->method('setDirection')->willReturnSelf();
        $sortOrders->method('create')->willReturn(new SortOrder());

        return new PostsResolver(
            $repo,
            $this->criteriaBuilder(),
            $this->createStub(FilterGroupBuilder::class),
            $sortOrders,
            $this->visibility(3),
            $resource
        );
    }

    public function testPostsResolverUnfilteredListing(): void
    {
        $repo = $this->createStub(PostRepositoryInterface::class);
        $repo->method('getList')->willReturn($this->results([$this->makeModel(Post::class, ['title' => 'A'])], 21));
        $connection = $this->connectionStub();

        $out = $this->postsResolver($repo, $connection)->resolve($this->field(), null, $this->info(), null, ['pageSize' => 500, 'currentPage' => 0]);

        $this->assertSame([['title' => 'A']], $out['items']);
        $this->assertSame(21, $out['total_count']);
        $this->assertSame(['page_size' => 100, 'current_page' => 1, 'total_pages' => 1], $out['page_info']);
        $this->assertSame([['status', 'published', 'eq'], ['store_id', '3', 'eq']], $this->filters);
    }

    public function testPostsResolverFilteredByTaxonomy(): void
    {
        $repo = $this->createStub(PostRepositoryInterface::class);
        $repo->method('getList')->willReturn($this->results([], 0));
        $connection = $this->connectionStub();
        $connection->method('fetchCol')->willReturn(['4', '4', '7']);

        $out = $this->postsResolver($repo, $connection)->resolve($this->field(), null, $this->info(), null, [
            'pageSize' => 5,
            'filter' => ['category_url_key' => 'news', 'tag_url_key' => 'php', 'author_url_key' => 'jane'],
        ]);

        $this->assertSame(0, $out['page_info']['total_pages']);
        $this->assertContains(['post_id', [4, 7], 'in'], $this->filters);
    }

    public function testPostsResolverReturnsEmptyWhenFilterMatchesNothing(): void
    {
        $repo = $this->createMock(PostRepositoryInterface::class);
        $repo->expects($this->never())->method('getList');
        $connection = $this->connectionStub();
        $connection->method('fetchCol')->willReturn([]);

        $out = $this->postsResolver($repo, $connection)->resolve($this->field(), null, $this->info(), null, ['filter' => ['tag_url_key' => 'none']]);
        $this->assertSame(['items' => [], 'total_count' => 0, 'page_info' => ['page_size' => 10, 'current_page' => 1, 'total_pages' => 0]], $out);
    }

    public function testPostsResolverSwallowsErrors(): void
    {
        $repo = $this->createStub(PostRepositoryInterface::class);
        $repo->method('getList')->willThrowException(new \RuntimeException('x'));
        $out = $this->postsResolver($repo, $this->connectionStub())->resolve($this->field(), null, $this->info(), null, ['currentPage' => 3]);
        $this->assertSame([], $out['items']);
        $this->assertSame(3, $out['page_info']['current_page']);
    }
}
