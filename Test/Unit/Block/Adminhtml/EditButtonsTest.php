<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Block\Adminhtml;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EditButtonsTest extends TestCase
{
    public static function entities(): array
    {
        return [
            'author' => ['Author', 'author'],
            'category' => ['Category', 'category'],
            'comment' => ['Comment', 'comment'],
            'post' => ['Post', 'post'],
            'tag' => ['Tag', 'tag'],
        ];
    }

    public static function entityNames(): array
    {
        return array_map(static fn (array $row) => [$row[0]], self::entities());
    }

    private function context(mixed $id): Context
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(static fn ($name) => $name === 'id' ? $id : null);
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(static function ($route, $params = []) {
            return 'https://admin.test/' . $route . ($params ? '?' . http_build_query($params) : '');
        });
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getUrlBuilder')->willReturn($url);
        return $context;
    }

    private function button(string $entity, string $type, mixed $id = null): object
    {
        $class = 'Panth\\Blog\\Block\\Adminhtml\\' . $entity . '\\Edit\\Button\\' . $type;
        return new $class($this->context($id));
    }

    #[DataProvider('entities')]
    public function testDeleteButtonOnlyForExistingEntity(string $entity, string $noun): void
    {
        $this->assertSame([], $this->button($entity, 'Delete')->getButtonData());
        $this->assertSame([], $this->button($entity, 'Delete', '0')->getButtonData());

        $data = $this->button($entity, 'Delete', '12')->getButtonData();
        $this->assertSame('delete', $data['class']);
        $this->assertSame(20, $data['sort_order']);
        $this->assertStringContainsString('Are you sure you want to delete this ' . $noun . '?', $data['on_click']);
        $this->assertStringContainsString('https://admin.test/*/*/delete?id=12', $data['on_click']);
    }

    #[DataProvider('entityNames')]
    public function testEntityIdIsCast(string $entity): void
    {
        $this->assertSame(7, $this->button($entity, 'Back', '7')->getEntityId());
        $this->assertNull($this->button($entity, 'Back', '')->getEntityId());
    }

    #[DataProvider('entityNames')]
    public function testStaticButtons(string $entity): void
    {
        $back = $this->button($entity, 'Back')->getButtonData();
        $this->assertSame("location.href = 'https://admin.test/*/*/';", $back['on_click']);
        $this->assertSame(10, $back['sort_order']);

        $reset = $this->button($entity, 'Reset')->getButtonData();
        $this->assertSame('location.reload();', $reset['on_click']);

        $save = $this->button($entity, 'Save')->getButtonData();
        $this->assertSame('save', $save['data_attribute']['mage-init']['button']['event']);
        $this->assertSame(90, $save['sort_order']);

        $continue = $this->button($entity, 'SaveAndContinue')->getButtonData();
        $this->assertSame('saveAndContinueEdit', $continue['data_attribute']['mage-init']['button']['event']);
        $this->assertSame(80, $continue['sort_order']);
    }
}
