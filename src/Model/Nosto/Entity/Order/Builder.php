<?php declare(strict_types=1);

namespace Od\NostoIntegration\Model\Nosto\Entity\Order;

use Nosto\Model\Order\Order as NostoOrder;
use Nosto\Model\Order\OrderStatus;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

class Builder
{
    public function build(OrderEntity $order, SalesChannelContext $context): NostoOrder
    {
        $channelId = $context->getSalesChannelId();
        $nostoOrder = new NostoOrder();
        $nostoOrder->setOrderNumber($order->getOrderNumber());
        $orderCreated = $order->getCreatedAt();
        //TODO add order payment
//        if ($order instanceof OrderPaymentInterface) {
//            $nostoOrder->setPaymentProvider($order->getPayment()->getMethod());
//        } else {
//            throw new NostoException('Order has no payment associated');
//        }

        $orderStatus = new OrderStatus();
        $orderStatus->loadData($order);
        $this->setOrderStatus($orderStatus);

        if ($order->getStateMachineState()) {
                $nostoStatus = new OrderStatus();
                $nostoStatus->setCode($order->getStateMachineState()->getTechnicalName());
                $nostoStatus->setDate($order->getUpdatedAt());
                $nostoOrder->setOrderStatus($nostoStatus);
            }
    }
}