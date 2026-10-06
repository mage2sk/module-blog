<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Ui\Component\Listing;

use Magento\Framework\Api\Filter;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\Collection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Panth\Blog\Model\ResourceModel\Post\Collection as PostCollection;
use Panth\Blog\Test\Unit\Support\BlogTestHelpers;
use Panth\Blog\Ui\Component\Listing\Columns\CommentPost;
use Panth\Blog\Ui\Component\Listing\Columns\PostActions;
use Panth\Blog\Ui\Component\Listing\LikeFulltextFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ListingComponentsTest extends TestCase
{
    use BlogTestHelpers;

    private function url(): UrlInterface
    {
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(static fn ($route, $params) => $route . '/id/' . $params['id']);
        return $url;
    }

    public static function actionColumns(): array
    {
        return [
            ['AuthorActions', 'author_id', 'panth_blog/author', 'author', false],
            ['CategoryActions', 'category_id', 'panth_blog/category', 'category', false],
            ['CommentActions', 'comment_id', 'panth_blog/comment', 'comment', false],
            ['TagActions', 'tag_id', 'panth_blog/tag', 'tag', false],
            ['PostActions', 'post_id', 'panth_blog/post', 'post', true],
        ];
    }

    #[DataProvider('actionColumns')]
    public function testActionColumnsBuildLinks(string $short, string $idField, string $route, string $noun, bool $duplicate): void
    {
        $class = 'Panth\\Blog\\Ui\\Component\\Listing\\Columns\\' . $short;
        $column = new $class(
            $this->createStub(ContextInterface::class),
            $this->createStub(UiComponentFactory::class),
            $this->url(),
            [],
            ['name' => 'actions']
        );

        $result = $column->prepareDataSource(['data' => ['items' => [[$idField => '5'], ['other' => 1]]]]);
        $actions = $result['data']['items'][0]['actions'];

        $this->assertSame($route . '/edit/id/5', $actions['edit']['href']);
        $this->assertSame($route . '/delete/id/5', $actions['delete']['href']);
        $this->assertTrue($actions['delete']['post']);
        $this->assertStringContainsString('delete this ' . $noun, (string) $actions['delete']['confirm']['message']);
        $this->assertSame($duplicate, isset($actions['duplicate']));
        $this->assertArrayNotHasKey('actions', $result['data']['items'][1]);

        $this->assertSame(['data' => []], $column->prepareDataSource(['data' => []]));
    }

    public function testPostActionsDuplicateLink(): void
    {
        $column = new PostActions(
            $this->createStub(ContextInterface::class),
            $this->createStub(UiComponentFactory::class),
            $this->url(),
            [],
            ['name' => 'actions']
        );
        $result = $column->prepareDataSource(['data' => ['items' => [['post_id' => 9]]]]);
        $this->assertSame('panth_blog/post/duplicate/id/9', $result['data']['items'][0]['actions']['duplicate']['href']);
    }

    public function testCommentPostColumnReplacesIdsWithTitles(): void
    {
        $connection = $this->connectionStub();
        $connection->method('fetchPairs')->willReturn([3 => 'Hello', 4 => 'World']);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $column = new CommentPost(
            $this->createStub(ContextInterface::class),
            $this->createStub(UiComponentFactory::class),
            $resource,
            [],
            ['name' => 'post_id']
        );

        $result = $column->prepareDataSource(['data' => ['items' => [
            ['post_id' => '3'],
            ['post_id' => 99],
            ['post_id' => null],
        ]]]);

        $this->assertSame(['Hello (#3)', 99, null], array_column($result['data']['items'], 'post_id'));
        $this->assertSame(['data' => ['items' => []]], $column->prepareDataSource(['data' => ['items' => []]]));
    }

    public function testCommentPostColumnSkipsQueryWithoutIds(): void
    {
        $resource = $this->createMock(ResourceConnection::class);
        $resource->expects($this->never())->method('getConnection');
        $column = new CommentPost(
            $this->createStub(ContextInterface::class),
            $this->createStub(UiComponentFactory::class),
            $resource,
            [],
            ['name' => 'post_id']
        );
        $items = ['data' => ['items' => [['post_id' => 0]]]];
        $this->assertSame($items, $column->prepareDataSource($items));
    }

    public function testLikeFilterBuildsEscapedOrCondition(): void
    {
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('quoteIdentifier')->willReturnCallback(static fn ($c) => '`' . $c . '`');
        $connection->method('quoteInto')->willReturnCallback(static fn ($text, $value) => str_replace('?', "'" . $value . "'", $text));

        $select = $this->createMock(Select::class);
        $select->expects($this->once())->method('where')
            ->with("`title` LIKE '%50\\%\\_off%' OR `url_key` LIKE '%50\\%\\_off%'");

        $collection = $this->createStub(PostCollection::class);
        $collection->method('getConnection')->willReturn($connection);
        $collection->method('getSelect')->willReturn($select);

        (new LikeFulltextFilter(['title', 5, 'url_key']))->apply($collection, new Filter(['value' => ' 50%_off ']));
    }

    public function testLikeFilterIgnoresEmptyValuesAndPlainCollections(): void
    {
        $collection = $this->createMock(PostCollection::class);
        $collection->expects($this->never())->method('getSelect');
        (new LikeFulltextFilter(['title']))->apply($collection, new Filter(['value' => '   ']));
        (new LikeFulltextFilter(['title']))->apply($collection, new Filter(['value' => ['array']]));
        (new LikeFulltextFilter([]))->apply($collection, new Filter(['value' => 'x']));

        $plain = $this->createMock(Collection::class);
        $plain->expects($this->never())->method('getSize');
        (new LikeFulltextFilter(['title']))->apply($plain, new Filter(['value' => 'x']));
    }
}
