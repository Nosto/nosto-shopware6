<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Order;

use Nosto\Model\Cart\LineItem as NostoLineItem;
use Nosto\Model\Order\Buyer;
use Nosto\Model\Order\Order as NostoOrder;
use Nosto\Model\Order\OrderStatus;
use Nosto\NostoException;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Model\Nosto\Entity\Order\Event\NostoOrderBuiltEvent;
use Nosto\NostoIntegration\Model\Nosto\Entity\Order\Item\Builder as NostoOrderItemBuilder;
use Nosto\NostoIntegration\Model\Nosto\Entity\Person\BuilderInterface as NostoBuyerBuilderInterface;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\ProductProviderInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class Builder
{
    public function __construct(
        private readonly NostoBuyerBuilderInterface $buyerBuilder,
        private readonly NostoOrderItemBuilder $nostoOrderItemBuilder,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SystemConfigService $systemConfigService,
        private readonly ConfigProvider $configProvider,
        private readonly EntityRepository $productRepository,
        private readonly ProductHelper $productHelper,
        private readonly ProductProviderInterface $productProvider,
    ) {
    }

    public function build(
        OrderEntity $order,
        SalesChannelContext $context,
    ): NostoOrder {
        $nostoOrder = new NostoOrder();
        $nostoOrder->setOrderNumber($order->getOrderNumber());
        $nostoOrder->setExternalOrderRef($order->getId());
        $orderCreated = $order->getCreatedAt()->format('Y-m-d H:i:s');
        $nostoOrder->setCreatedAt($orderCreated);
        if ($order->getTransactions() instanceof OrderTransactionCollection) {
            $nostoOrder->setPaymentProvider(
                (string) $order->getTransactions()?->first()?->getPaymentMethod()?->getTranslation('name'),
            );
        } else {
            throw new NostoException('Order has no payment associated');
        }
        if ($order->getStateMachineState()) {
            $nostoStatus = new OrderStatus();
            $nostoStatus->setCode($order->getStateMachineState()->getTechnicalName());
            $nostoStatus->setLabel($order->getStateMachineState()->getTranslation('name'));
            $nostoStatus->setDate($order->getCreatedAt()->format('Y-m-d H:i:s'));
            $nostoOrder->setOrderStatus($nostoStatus);
            $nostoOrder->addOrderStatus($nostoStatus);
        }
        $nostoBuyer = $this->buyerBuilder->fromOrder($order);
        if ($nostoBuyer instanceof Buyer) {
            $nostoOrder->setCustomer($nostoBuyer);
        }
        foreach ($order->getLineItems() as $item) {
            if ($item->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE) {
                $nostoItem = $this->nostoOrderItemBuilder->build(
                    $item,
                    $order->getCurrency(),
                    $this->productRepository,
                    $context,
                    $this->configProvider,
                    $this->systemConfigService,
                    $this->productHelper,
                    $this->productProvider,
                );
                $nostoOrder->addPurchasedItems($nostoItem);
            }
        }
        if (($shippingInclTax = $order->getShippingTotal()) > 0) {
            $nostoItem = new NostoLineItem();
            $nostoItem->loadSpecialItemData(
                'Shipping and handling',
                $shippingInclTax,
                $order->getCurrency()->getIsoCode(),
            );
            $nostoOrder->addPurchasedItems($nostoItem);
        }

        $this->eventDispatcher->dispatch(new NostoOrderBuiltEvent($order, $nostoOrder, $context->getContext()));
        return $nostoOrder;
    }
}
