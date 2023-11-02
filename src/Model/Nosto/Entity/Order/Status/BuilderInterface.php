<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Order\Status;

use Nosto\Model\Order\GraphQL\OrderStatus as NostoOrderStatus;
use Shopware\Core\Checkout\Order\OrderEntity;

interface BuilderInterface
{
    public function build(OrderEntity $order): ?NostoOrderStatus;
}
