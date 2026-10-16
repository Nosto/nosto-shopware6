<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Model\Nosto\Entity\Order;

use Nosto\Model\Order\Buyer;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Model\Nosto\Entity\Order\Builder as OrderBuilder;
use Nosto\NostoIntegration\Model\Nosto\Entity\Order\Buyer\Builder as BuyerBuilder;
use Nosto\NostoIntegration\Model\Nosto\Entity\Order\Item\Builder as OrderItemBuilder;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class BuilderTest extends TestCase
{
    public function testBuyerIsAttachedWhenCustomerDataToNostoEnabled(): void
    {
        $buyerBuilder = $this->createMock(BuyerBuilder::class);
        $buyerBuilder->expects($this->once())
            ->method('fromOrder')
            ->willReturn(new Buyer());

        $order = $this->createOrder();
        $nostoOrder = $this->createBuilder($buyerBuilder, customerDataEnabled: true)
            ->build($order, $this->createSalesChannelContext());

        self::assertInstanceOf(Buyer::class, $nostoOrder->getCustomer());
    }

    public function testBuyerIsNotAttachedWhenCustomerDataToNostoDisabled(): void
    {
        $buyerBuilder = $this->createMock(BuyerBuilder::class);
        $buyerBuilder->expects($this->never())
            ->method('fromOrder');

        $order = $this->createOrder();
        $nostoOrder = $this->createBuilder($buyerBuilder, customerDataEnabled: false)
            ->build($order, $this->createSalesChannelContext());

        self::assertNull($nostoOrder->getCustomer());
    }

    private function createBuilder(
        BuyerBuilder $buyerBuilder,
        bool $customerDataEnabled,
    ): OrderBuilder {
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('shouldSendCustomerDataFromBackend')->willReturn($customerDataEnabled);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        return new OrderBuilder(
            $buyerBuilder,
            $this->createMock(OrderItemBuilder::class),
            $eventDispatcher,
            $this->createMock(SystemConfigService::class),
            $configProvider,
            $this->createMock(EntityRepository::class),
            $this->createMock(ProductHelper::class),
            $this->createMock(PartialProvider::class),
        );
    }

    private function createOrder(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setOrderNumber('10001');
        $order->setCreatedAt(new \DateTimeImmutable());
        $order->setTransactions(new OrderTransactionCollection());
        $order->setLineItems(new \Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection());
        $order->setShippingTotal(0.0);

        return $order;
    }

    private function createSalesChannelContext(): SalesChannelContext
    {
        $context = Context::createDefaultContext();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);
        $salesChannelContext->method('getSalesChannelId')->willReturn(Uuid::randomHex());

        return $salesChannelContext;
    }
}
