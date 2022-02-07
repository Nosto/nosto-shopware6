<?php declare(strict_types=1);

namespace Od\NostoIntegration\Model\Nosto\Entity\Order;

use DateTimeInterface;
use Nosto\Model\Cart\LineItem as NostoLineItem;
use Nosto\Model\Customer;
use Nosto\Model\Order\Buyer;
use Nosto\Model\Order\Order as NostoOrder;
use Nosto\Model\Order\OrderStatus;
use Nosto\NostoException;
use Od\NostoIntegration\Model\Nosto\Entity\Order\Buyer\Builder as NostoBuyerBuilder;
use Od\NostoIntegration\Model\Nosto\Entity\Order\Item\Builder as NostoOrderItemBuilder;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

class Builder
{
    private NostoBuyerBuilder $buyerBuilder;
    private NostoOrderItemBuilder $nostoOrderItemBuilder;

    public function __construct(
        NostoBuyerBuilder $buyerBuilder,
        NostoOrderItemBuilder $nostoOrderItemBuilder
    ) {
        $this->buyerBuilder = $buyerBuilder;
        $this->nostoOrderItemBuilder = $nostoOrderItemBuilder;
    }

    public function build(OrderEntity $order): NostoOrder
    {
        $nostoOrder = new NostoOrder();
        $nostoOrder->setOrderNumber($order->getOrderNumber());
        $nostoOrder->setExternalOrderRef($order->getId());
        $orderCreated = $order->getCreatedAt()->format('Y-m-d H:i:s');
        $nostoOrder->setCreatedAt($orderCreated);
        if ($order->getTransactions() instanceof OrderTransactionCollection) {
            $nostoOrder->setPaymentProvider((string)$order->getTransactions()->first()->getPaymentMethod()->getName());
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
                $nostoItem = $this->nostoOrderItemBuilder->build($item,$order->getCurrency());
                $nostoOrder->addPurchasedItems($nostoItem);
            }
        }
        if (($shippingInclTax = $order->getShippingTotal()) > 0) {
            $nostoItem = new NostoLineItem();
            $nostoItem->loadSpecialItemData(
                'Shipping and handling',
                $shippingInclTax === null ? 0 : $shippingInclTax,
                $order->getCurrency()->getIsoCode()
            );
            $nostoOrder->addPurchasedItems($nostoItem);
        }

        return $nostoOrder;
    }
}