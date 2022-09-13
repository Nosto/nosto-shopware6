<?php

declare(strict_types=1);

namespace Od\NostoIntegration\Model\Nosto\Entity\Order;

use Nosto\Model\Order\Order as NostoOrder;
use Shopware\Core\Checkout\Order\OrderEntity;

interface BuilderInterface
{
    public function build(OrderEntity $order): NostoOrder;
}
