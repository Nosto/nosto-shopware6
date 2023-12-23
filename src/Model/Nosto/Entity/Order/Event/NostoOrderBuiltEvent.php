<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Order\Event;

use Nosto\Model\Order\Order as NostoOrder;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class NostoOrderBuiltEvent extends NestedEvent
{
    public function __construct(
        private readonly OrderEntity $order,
        private readonly NostoOrder $nostoOrder,
        private readonly Context $context,
    ) {
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
        return $this->context;
    }
}
