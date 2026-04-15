<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\EventListener;

use Closure;
use Nosto\NostoIntegration\Async\EventsWriter;
use Nosto\NostoIntegration\EventListener\ProductWrittenDeletedEvent;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;

final class ProductWrittenDeletedEventTest extends TestCase
{
    public function testOnProductWrittenWritesChangelogEntriesForMappedIds(): void
    {
        $context = Context::createDefaultContext();
        $event = $this->createMock(EntityWrittenEvent::class);
        $event->method('getIds')->willReturn(['product-id-1', 'product-id-2']);
        $event->method('getContext')->willReturn($context);
        $event->method('getEntityName')->willReturn(ProductDefinition::ENTITY_NAME);

        $productHelper = $this->createMock(ProductHelper::class);
        $productHelper->expects($this->once())
            ->method('loadOrderNumberMapping')
            ->with(['product-id-1', 'product-id-2'], $context)
            ->willReturn([
                'product-id-1' => 'SWDEMO10001',
                'product-id-2' => 'SWDEMO10002',
            ]);

        $eventsWriter = $this->createMock(EventsWriter::class);
        $calls = [];
        $eventsWriter->expects($this->exactly(2))
            ->method('writeEvent')
            ->willReturnCallback(static function (
                string $entityName,
                string $entityId,
                Context $callContext,
                ?string $productNumber = null,
            ) use (&$calls): void {
                $calls[] = [$entityName, $entityId, $callContext, $productNumber];
            });

        $listener = new ProductWrittenDeletedEvent(
            $eventsWriter,
            $productHelper,
            $this->createMock(ConfigProvider::class),
        );

        $listener->onProductWritten($event);

        self::assertSame([
            [ProductDefinition::ENTITY_NAME, 'product-id-1', $context, 'SWDEMO10001'],
            [ProductDefinition::ENTITY_NAME, 'product-id-2', $context, 'SWDEMO10002'],
        ], $calls);
    }

    public function testBeforeDeleteWritesChangelogEntriesAfterSuccessfulDelete(): void
    {
        $context = Context::createDefaultContext();
        $event = $this->createMock(EntityDeleteEvent::class);
        $event->method('getIds')->with(ProductDefinition::ENTITY_NAME)->willReturn(['product-id-1']);
        $event->method('getContext')->willReturn($context);

        $capturedSuccessCallback = null;
        $event->expects($this->once())
            ->method('addSuccess')
            ->willReturnCallback(static function (Closure $callback) use (&$capturedSuccessCallback): void {
                $capturedSuccessCallback = $callback;
            });

        $productHelper = $this->createMock(ProductHelper::class);
        $productHelper->expects($this->once())
            ->method('loadOrderNumberMapping')
            ->with(['product-id-1'], $context)
            ->willReturn([
                'product-id-1' => 'SWDEMO10001',
            ]);

        $eventsWriter = $this->createMock(EventsWriter::class);
        $eventsWriter->expects($this->once())
            ->method('writeEvent')
            ->with(ProductDefinition::ENTITY_NAME, 'product-id-1', $context, 'SWDEMO10001');

        $listener = new ProductWrittenDeletedEvent(
            $eventsWriter,
            $productHelper,
            $this->createMock(ConfigProvider::class),
        );

        $listener->beforeDelete($event);
        self::assertInstanceOf(Closure::class, $capturedSuccessCallback);
        $capturedSuccessCallback();
    }

    public function testBeforeDeleteSkipsChangelogLookupWhenNoProductsAreDeleted(): void
    {
        $event = $this->createMock(EntityDeleteEvent::class);
        $event->method('getIds')->with(ProductDefinition::ENTITY_NAME)->willReturn([]);

        $productHelper = $this->createMock(ProductHelper::class);
        $productHelper->expects($this->never())
            ->method('loadOrderNumberMapping');

        $eventsWriter = $this->createMock(EventsWriter::class);
        $eventsWriter->expects($this->never())
            ->method('writeEvent');

        $listener = new ProductWrittenDeletedEvent(
            $eventsWriter,
            $productHelper,
            $this->createMock(ConfigProvider::class),
        );

        $listener->beforeDelete($event);
    }
}
