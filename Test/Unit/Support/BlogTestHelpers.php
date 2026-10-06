<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Support;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Registry;

trait BlogTestHelpers
{
    protected function selectStub(): Select
    {
        $select = $this->createStub(Select::class);
        foreach (['from', 'where', 'orWhere', 'order', 'limit', 'join', 'joinLeft', 'joinInner', 'columns', 'group', 'reset', 'distinct', 'having'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $select->method('__toString')->willReturn('SELECT 1');
        return $select;
    }

    protected function connectionStub(?Select $select = null): AdapterInterface
    {
        $select ??= $this->selectStub();
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        return $connection;
    }

    /**
     * @template T
     * @param class-string<T> $class
     * @return T
     */
    protected function makeModel(string $class, array $data = [], mixed $resource = null): object
    {
        if ($resource === null) {
            $resource = $this->createStub(AbstractDb::class);
            $resource->method('getIdFieldName')->willReturn('id');
        }
        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(\Magento\Framework\Event\ManagerInterface::class));
        $model = new $class(
            $context,
            $this->createStub(Registry::class),
            $resource
        );
        $model->setData($data);
        return $model;
    }
}
