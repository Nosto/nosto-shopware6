<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Order\Item;

use Nosto\Model\Cart\LineItem as NostoLineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\System\Currency\CurrencyEntity;

interface BuilderInterface
{
    public function build(OrderLineItemEntity $item, CurrencyEntity $currency): NostoLineItem;
}
