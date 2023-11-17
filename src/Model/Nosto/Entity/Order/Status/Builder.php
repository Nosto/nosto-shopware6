<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Order\Status;

use Exception;
use Nosto\Model\Order\GraphQL\OrderStatus as NostoOrderStatus;
use Nosto\NostoException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\OrderEntity;

class Builder implements BuilderInterface
{
    public function build(OrderEntity $order): ?NostoOrderStatus
    {
        $orderNumber = $order->getOrderNumber();
        $orderStatus = $order->getStateMachineState()->getTechnicalName();
        $updatedAt = $order->getCreatedAt()->format('Y-m-d H:i:s');
        try {
            if ($order->getTransactions() instanceof OrderTransactionCollection) {
                $paymentProvider = $order->getTransactions()->first()->getPaymentMethod()->getName();
            } else {
                throw new NostoException('Order has no payment associated');
            }
            $nostoOrderStatus = new NostoOrderStatus(
                $orderNumber,
                $orderStatus,
                $paymentProvider,
                $updatedAt
            );

            return $nostoOrderStatus;
        } catch (Exception $e) {
            throw new Exception('Unable to build product, reason: ' . $e->getMessage());
        }

        return null;
    }
}
