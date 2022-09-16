<?php

declare(strict_types=1);

namespace Od\NostoIntegration\Model\Nosto\Entity\Order\Event;

use Nosto\Model\Order\Order as NostoOrder;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;


class NostoOrderBuiltEvent extends NestedEvent
{
    private OrderEntity $order;
    private NostoOrder $nostoOrder;

    public function __construct(
        OrderEntity $order,
        NostoOrder $nostoOrder
    ) {
        $this->order = $order;
        $this->nostoOrder = $nostoOrder;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getNostoOrder(): NostoOrder
    {
        return $this->nostoOrder;
    }

    public function getContext(): Context
    {
        // TODO see on OrderSyncHandler
        return Context::createDefaultContext();
    }
}
