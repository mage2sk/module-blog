<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model;

use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Panth\Blog\Api\Data\CommentSearchResultsInterface;
use Panth\Blog\Api\Data\CommentSearchResultsInterfaceFactory;
use Panth\Blog\Api\Data\TagSearchResultsInterfaceFactory;
use Panth\Blog\Model\CommentFactory;
use Panth\Blog\Model\CommentRepository;
use Panth\Blog\Model\ResourceModel\Comment as CommentResource;
use Panth\Blog\Model\ResourceModel\Comment\Collection as CommentCollection;
use Panth\Blog\Model\ResourceModel\Comment\CollectionFactory as CommentCollectionFactory;
use Panth\Blog\Model\ResourceModel\Tag as TagResource;
use Panth\Blog\Model\ResourceModel\Tag\Collection as TagCollection;
use Panth\Blog\Model\ResourceModel\Tag\CollectionFactory as TagCollectionFactory;
use Panth\Blog\Model\Tag;
use Panth\Blog\Model\TagFactory;
use Panth\Blog\Model\TagRepository;
use Panth\Blog\Model\Url\SlugGenerator;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use PHPUnit\Framework\TestCase;

class RepositoryLookupTest extends TestCase
{
    use BlogTestHelpers;

    public function testTagGetByNameLooksUpTheSluggedName(): void
    {
        $tag = $this->makeModel(Tag::class);
        $tag->setId(11);

        $collection = $this->createMock(TagCollection::class);
        $collection->expects($this->once())->method('addFieldToFilter')->with('url_key', 'hyva-themes')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('setCurPage')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($tag);

        $collectionFactory = $this->createStub(TagCollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $repository = new TagRepository(
            $this->createStub(TagResource::class),
            $this->createStub(TagFactory::class),
            $collectionFactory,
            $this->createStub(TagSearchResultsInterfaceFactory::class),
            $this->createStub(CollectionProcessorInterface::class),
            new SlugGenerator()
        );

        $this->assertSame($tag, $repository->getByName('The Hyva Themes'));
    }

    private function commentRepository(SearchCriteriaBuilder $criteriaBuilder, array &$captured): CommentRepository
    {
        $state = [];
        $filterBuilder = $this->createStub(FilterBuilder::class);
        $filterBuilder->method('setField')->willReturnCallback(function ($v) use (&$state, &$filterBuilder) {
            $state['field'] = $v;
            return $filterBuilder;
        });
        $filterBuilder->method('setConditionType')->willReturnCallback(function ($v) use (&$state, &$filterBuilder) {
            $state['condition_type'] = $v;
            return $filterBuilder;
        });
        $filterBuilder->method('setValue')->willReturnCallback(function ($v) use (&$state, &$filterBuilder) {
            $state['value'] = $v;
            return $filterBuilder;
        });
        $filterBuilder->method('create')->willReturnCallback(function () use (&$state) {
            $filter = new Filter($state);
            $state = [];
            return $filter;
        });

        $collection = $this->createStub(CommentCollection::class);
        $collection->method('getItems')->willReturn([]);
        $collection->method('getSize')->willReturn(0);
        $collectionFactory = $this->createStub(CommentCollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $results = $this->createStub(CommentSearchResultsInterface::class);
        $resultsFactory = $this->createStub(CommentSearchResultsInterfaceFactory::class);
        $resultsFactory->method('create')->willReturn($results);
        $captured['results'] = $results;

        return new CommentRepository(
            $this->createStub(CommentResource::class),
            $this->createStub(CommentFactory::class),
            $collectionFactory,
            $resultsFactory,
            $this->createStub(CollectionProcessorInterface::class),
            $criteriaBuilder,
            $filterBuilder,
            $this->createStub(FilterGroupBuilder::class)
        );
    }

    public function testCommentListByPostFiltersByPostAndStatus(): void
    {
        $calls = [];
        $criteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $criteriaBuilder->expects($this->exactly(2))->method('addFilter')
            ->willReturnCallback(function ($field, $value, $type) use (&$calls, &$criteriaBuilder) {
                $calls[] = [$field, $value, $type];
                return $criteriaBuilder;
            });
        $criteriaBuilder->expects($this->once())->method('create')
            ->willReturn($this->createStub(SearchCriteriaInterface::class));

        $captured = [];
        $result = $this->commentRepository($criteriaBuilder, $captured)->getListByPost(7, 'approved');

        $this->assertSame($captured['results'], $result);
        $this->assertSame([['post_id', 7, 'eq'], ['status', 'approved', 'eq']], $calls);
    }

    public function testCommentListByPostWithoutStatusOnlyFiltersPost(): void
    {
        $calls = [];
        $criteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $criteriaBuilder->expects($this->once())->method('addFilter')
            ->willReturnCallback(function ($field, $value, $type) use (&$calls, &$criteriaBuilder) {
                $calls[] = [$field, $value, $type];
                return $criteriaBuilder;
            });
        $criteriaBuilder->method('create')->willReturn($this->createStub(SearchCriteriaInterface::class));

        $captured = [];
        $this->commentRepository($criteriaBuilder, $captured)->getListByPost(3, '');
        $this->assertSame([['post_id', 3, 'eq']], $calls);
    }
}
