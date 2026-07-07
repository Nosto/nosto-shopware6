<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Model\Operation;

use Nosto\NostoIntegration\Async\OrderSyncMessage;
use Nosto\NostoIntegration\Model\Nosto\Account;
use Nosto\NostoIntegration\Model\Nosto\Account\KeyChain;
use Nosto\NostoIntegration\Model\Nosto\Account\Provider as AccountProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Order\Builder as OrderBuilder;
use Nosto\NostoIntegration\Model\Nosto\Entity\Order\Status\Builder as OrderStatusBuilder;
use Nosto\NostoIntegration\Model\Operation\OrderSyncHandler;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class OrderSyncHandlerTest extends TestCase
{
    public function testExecuteScopesOrderLookup(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $changelogId = Uuid::randomHex();

        $orderRepository = $this->createMock(EntityRepository::class);
        $orderRepository->expects($this->once())
            ->method('search')
            ->willReturnCallback(
                static function (Criteria $criteria, Context $searchContext) use (
                    $context,
                    $orderId,
                    $salesChannelId
                ): EntitySearchResult {
                    self::assertSame($context, $searchContext);
                    self::assertSame([$orderId], self::getEqualsAnyFilterValue($criteria, 'id'));
                    self::assertSame($context->getLanguageId(), self::getEqualsFilterValue($criteria, 'languageId'));
                    self::assertSame($salesChannelId, self::getEqualsFilterValue($criteria, 'salesChannelId'));

                    return self::orderResult($criteria, $searchContext);
                },
            );

        $handler = $this->createHandler(
            $context,
            salesChannelId: $salesChannelId,
            orderRepository: $orderRepository,
        );

        $result = $handler->execute(new OrderSyncMessage(
            Uuid::randomHex(),
            Uuid::randomHex(),
            [],
            [
                $changelogId => $orderId,
            ],
            $context,
        ));

        self::assertFalse($result->hasErrors());
    }

    public function testExecuteReturnsSyncErrors(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $exceptionMessage = 'Unable to build order status';

        $order = new OrderEntity();
        $order->setId($orderId);

        $orderRepository = $this->createMock(EntityRepository::class);
        $orderRepository->method('search')->willReturnCallback(
            static fn (Criteria $criteria, Context $searchContext): EntitySearchResult => self::orderResult(
                $criteria,
                $searchContext,
                $order,
            ),
        );

        $orderStatusBuilder = $this->createMock(OrderStatusBuilder::class);
        $orderStatusBuilder->expects($this->once())
            ->method('build')
            ->with($order)
            ->willThrowException(new \RuntimeException($exceptionMessage));

        $handler = $this->createHandler(
            $context,
            salesChannelId: $salesChannelId,
            orderRepository: $orderRepository,
            orderStatusBuilder: $orderStatusBuilder,
        );

        $result = $handler->execute(new OrderSyncMessage(
            Uuid::randomHex(),
            Uuid::randomHex(),
            [],
            [$orderId],
            $context,
        ));

        self::assertTrue($result->hasErrors());
        self::assertCount(1, $result->getErrors());
        self::assertSame($exceptionMessage, $result->getErrors()[0]->getMessage());
    }

    private function createHandler(
        Context $context,
        string $salesChannelId,
        ?EntityRepository $orderRepository = null,
        ?OrderStatusBuilder $orderStatusBuilder = null,
    ): OrderSyncHandler {
        $account = new Account($salesChannelId, $context->getLanguageId(), 'account', new KeyChain([]));

        $accountProvider = $this->createMock(AccountProvider::class);
        $accountProvider->method('all')->willReturn([$account]);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);
        $salesChannelContext->method('getSalesChannelId')->willReturn($salesChannelId);

        $contextFactory = $this->createMock(AbstractSalesChannelContextFactory::class);
        $contextFactory->expects($this->once())
            ->method('create')
            ->with(
                self::isString(),
                $salesChannelId,
                [
                    SalesChannelContextService::LANGUAGE_ID => $context->getLanguageId(),
                ],
            )
            ->willReturn($salesChannelContext);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        return new OrderSyncHandler(
            $contextFactory,
            $orderRepository ?? $this->createMock(EntityRepository::class),
            $accountProvider,
            $this->createMock(OrderBuilder::class),
            $orderStatusBuilder ?? $this->createMock(OrderStatusBuilder::class),
            $eventDispatcher,
        );
    }

    private static function orderResult(
        Criteria $criteria,
        Context $context,
        OrderEntity ...$orders,
    ): EntitySearchResult {
        return new EntitySearchResult(
            OrderEntity::class,
            count($orders),
            new OrderCollection($orders),
            new AggregationResultCollection(),
            $criteria,
            $context,
        );
    }

    /**
     * @return array<string|int|float|null>
     */
    private static function getEqualsAnyFilterValue(Criteria $criteria, string $field): array
    {
        foreach ($criteria->getFilters() as $filter) {
            if ($filter instanceof EqualsAnyFilter && $filter->getField() === $field) {
                return $filter->getValue();
            }
        }

        self::fail(sprintf('Expected EqualsAnyFilter for field "%s".', $field));
    }

    private static function getEqualsFilterValue(Criteria $criteria, string $field): string|bool|float|int|null
    {
        foreach ($criteria->getFilters() as $filter) {
            if ($filter instanceof EqualsFilter && $filter->getField() === $field) {
                return $filter->getValue();
            }
        }

        self::fail(sprintf('Expected EqualsFilter for field "%s".', $field));
    }
}
