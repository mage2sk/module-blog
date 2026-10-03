<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model\Api;

use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Exception\InputException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Blog\Model\Api\PublicReadGuard;
use PHPUnit\Framework\TestCase;

class PublicReadGuardCriteriaTest extends TestCase
{
    private function guard(?StoreManagerInterface $storeManager = null, ?AuthorizationInterface $auth = null): PublicReadGuard
    {
        $state = [];
        $filterBuilder = $this->createStub(FilterBuilder::class);
        foreach (['setField' => 'field', 'setValue' => 'value', 'setConditionType' => 'condition_type'] as $method => $key) {
            $filterBuilder->method($method)->willReturnCallback(function ($v) use (&$state, $key, &$filterBuilder) {
                $state[$key] = $v;
                return $filterBuilder;
            });
        }
        $filterBuilder->method('create')->willReturnCallback(function () use (&$state) {
            $f = new Filter($state);
            $state = [];
            return $f;
        });

        $groupFilters = [];
        $groupBuilder = $this->createStub(FilterGroupBuilder::class);
        $groupBuilder->method('setFilters')->willReturnCallback(function (array $filters) use (&$groupFilters, &$groupBuilder) {
            $groupFilters = $filters;
            return $groupBuilder;
        });
        $groupBuilder->method('create')->willReturnCallback(function () use (&$groupFilters) {
            return new FilterGroup(['filters' => $groupFilters]);
        });

        return new PublicReadGuard(
            $auth ?? $this->createStub(AuthorizationInterface::class),
            $storeManager ?? $this->createStub(StoreManagerInterface::class),
            $filterBuilder,
            $groupBuilder
        );
    }

    public function testAddRequiredFiltersAppendsOneGroupPerField(): void
    {
        $existing = new FilterGroup(['filters' => [new Filter(['field' => 'title', 'value' => 'x'])]]);
        $criteria = new SearchCriteria(['filter_groups' => [$existing]]);

        $this->guard()->addRequiredFilters($criteria, ['status' => 'published', 'store_id' => '2']);

        $groups = $criteria->getFilterGroups();
        $this->assertCount(3, $groups);
        $this->assertSame($existing, $groups[0]);
        $this->assertSame('status', $groups[1]->getFilters()[0]->getField());
        $this->assertSame('published', $groups[1]->getFilters()[0]->getValue());
        $this->assertSame('eq', $groups[1]->getFilters()[0]->getConditionType());
        $this->assertSame('2', $groups[2]->getFilters()[0]->getValue());
    }

    public function testAssertFieldsNotUsedRejectsPrivateFilterCaseInsensitively(): void
    {
        $criteria = new SearchCriteria(['filter_groups' => [
            new FilterGroup(['filters' => [new Filter(['field' => ' EMAIL ', 'value' => 'a'])]]),
        ]]);
        $this->expectException(InputException::class);
        $this->guard()->assertFieldsNotUsed($criteria, ['email']);
    }

    public function testAssertFieldsNotUsedRejectsPrivateSort(): void
    {
        $criteria = new SearchCriteria(['sort_orders' => [new SortOrder(['field' => 'user_id', 'direction' => 'ASC'])]]);
        $this->expectException(InputException::class);
        $this->guard()->assertFieldsNotUsed($criteria, ['user_id']);
    }

    public function testAssertFieldsNotUsedAllowsPublicFields(): void
    {
        $criteria = new SearchCriteria([
            'filter_groups' => [new FilterGroup(['filters' => [new Filter(['field' => 'display_name', 'value' => 'a'])]])],
            'sort_orders' => [new SortOrder(['field' => 'sort_order', 'direction' => 'ASC'])],
        ]);
        $this->guard()->assertFieldsNotUsed($criteria, ['email']);
        $this->assertCount(1, $criteria->getFilterGroups());
    }

    public function testGetStoreIdAndAuthorizationFailuresAreSafe(): void
    {
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn('4');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $this->assertSame(4, $this->guard($storeManager)->getStoreId());

        $broken = $this->createStub(StoreManagerInterface::class);
        $broken->method('getStore')->willThrowException(new \RuntimeException('x'));
        $auth = $this->createStub(AuthorizationInterface::class);
        $auth->method('isAllowed')->willThrowException(new \RuntimeException('no session'));
        $guard = $this->guard($broken, $auth);
        $this->assertSame(0, $guard->getStoreId());
        $this->assertFalse($guard->canReadAll('Panth_Blog::post'));
    }
}
