<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\EventListener;

use Closure;
use Nosto\NostoIntegration\Async\EventsWriter;
use Nosto\NostoIntegration\EventListener\ProductWrittenDeletedEvent;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Account;
use Nosto\NostoIntegration\Model\Nosto\Account\KeyChain;
use Nosto\NostoIntegration\Model\Nosto\Account\Provider as AccountProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

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
            $this->createMock(AccountProvider::class),
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
            $this->createMock(AccountProvider::class),
        );

        $listener->beforeDelete($event);
        self::assertInstanceOf(Closure::class, $capturedSuccessCallback);
        $capturedSuccessCallback();
    }

    public function testOnProductWrittenIncludesCurrentAndPreviousParentsWhenCalculationIsEnabled(): void
    {
        $context = Context::createDefaultContext();
        $childId = Uuid::randomHex();
        $oldParentId = Uuid::randomHex();
        $newParentId = Uuid::randomHex();
        $existence = new EntityExistence(
            ProductDefinition::ENTITY_NAME,
            [
                'id' => $childId,
            ],
            true,
            true,
            true,
            [
                'parent_id' => Uuid::fromHexToBytes($oldParentId),
            ],
        );
        $writeResult = new EntityWriteResult(
            $childId,
            [
                'parentId' => $newParentId,
            ],
            ProductDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_UPDATE,
            $existence,
        );
        $event = new EntityWrittenEvent(ProductDefinition::ENTITY_NAME, [$writeResult], $context);

        $productHelper = $this->createMock(ProductHelper::class);
        $productHelper->expects($this->once())
            ->method('loadOrderNumberMapping')
            ->with([$childId, $newParentId, $oldParentId], $context)
            ->willReturn([
                $childId => 'CHILD',
                $newParentId => 'NEW-PARENT',
                $oldParentId => 'OLD-PARENT',
            ]);

        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isEnabledCalculateParentStockFromVariants')
            ->with('sales-channel-id', 'language-id')
            ->willReturn(true);
        $accountProvider = $this->createMock(AccountProvider::class);
        $accountProvider->method('all')->with($context)->willReturn([
            new Account('sales-channel-id', 'language-id', 'account', new KeyChain([])),
        ]);

        $eventsWriter = $this->createMock(EventsWriter::class);
        $calls = [];
        $eventsWriter->expects($this->exactly(3))
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
            $configProvider,
            $accountProvider,
        );
        $listener->onProductWritten($event);

        self::assertSame([
            [ProductDefinition::ENTITY_NAME, $childId, $context, 'CHILD'],
            [ProductDefinition::ENTITY_NAME, $newParentId, $context, 'NEW-PARENT'],
            [ProductDefinition::ENTITY_NAME, $oldParentId, $context, 'OLD-PARENT'],
        ], $calls);
    }

    public function testBeforeDeleteIncludesParentWhenCalculationIsEnabled(): void
    {
        $context = Context::createDefaultContext();
        $childId = Uuid::randomHex();
        $parentId = Uuid::randomHex();
        $event = $this->createMock(EntityDeleteEvent::class);
        $event->method('getIds')->with(ProductDefinition::ENTITY_NAME)->willReturn([$childId]);
        $event->method('getContext')->willReturn($context);
        $existence = new EntityExistence(
            ProductDefinition::ENTITY_NAME,
            [
                'id' => $childId,
            ],
            true,
            true,
            true,
            [
                'parent_id' => Uuid::fromHexToBytes($parentId),
            ],
        );
        $command = $this->createMock(WriteCommand::class);
        $command->method('getEntityName')->willReturn(ProductDefinition::ENTITY_NAME);
        $command->method('getEntityExistence')->willReturn($existence);
        $event->method('getCommands')->willReturn([$command]);

        $capturedSuccessCallback = null;
        $event->expects($this->once())
            ->method('addSuccess')
            ->willReturnCallback(static function (Closure $callback) use (&$capturedSuccessCallback): void {
                $capturedSuccessCallback = $callback;
        });

        $productHelper = $this->createMock(ProductHelper::class);
        $productHelper->method('loadOrderNumberMapping')
            ->with([$childId, $parentId], $context)
            ->willReturn([
                $childId => 'CHILD',
                $parentId => 'PARENT',
            ]);

        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isEnabledCalculateParentStockFromVariants')->willReturn(true);
        $accountProvider = $this->createMock(AccountProvider::class);
        $accountProvider->method('all')->willReturn([
            new Account('sales-channel-id', 'language-id', 'account', new KeyChain([])),
        ]);

        $eventsWriter = $this->createMock(EventsWriter::class);
        $eventsWriter->expects($this->exactly(2))->method('writeEvent');

        $listener = new ProductWrittenDeletedEvent(
            $eventsWriter,
            $productHelper,
            $configProvider,
            $accountProvider,
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
            $this->createMock(AccountProvider::class),
        );

        $listener->beforeDelete($event);
    }

    public function testOnResponseVariesCachedNavigationResponsesByCookie(): void
    {
        $request = Request::create('/navigation/category-id');
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sales-channel-id');
        $context->method('getLanguageId')->willReturn('language-id');
        $request->attributes->set('sw-sales-channel-context', $context);

        $response = new Response();
        $response->setVary(['Accept-Language']);

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );

        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isCacheEnabled')->with('sales-channel-id', 'language-id')->willReturn(true);
        $configProvider->method('isNavigationEnabled')->with('sales-channel-id', 'language-id')->willReturn(true);
        $configProvider->method('getCacheTtl')->with('sales-channel-id', 'language-id')->willReturn(5);

        $listener = new ProductWrittenDeletedEvent(
            $this->createMock(EventsWriter::class),
            $this->createMock(ProductHelper::class),
            $configProvider,
            $this->createMock(AccountProvider::class),
        );

        $listener->onResponse($event);

        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertSame(['Accept-Language', 'Cookie'], $response->getVary());
        self::assertSame(300, $response->getMaxAge());
        self::assertSame(300, $response->getTtl());
    }
}
