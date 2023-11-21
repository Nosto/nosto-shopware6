<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Order\Item;

use Exception;
use Nosto\Model\Cart\LineItem as NostoLineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\System\Currency\CurrencyEntity;

class Builder implements BuilderInterface
{
    public function build(OrderLineItemEntity $item, CurrencyEntity $currency): NostoLineItem
    {
        $nostoItem = new NostoLineItem();
        $nostoItem->setPriceCurrencyCode($currency->getIsoCode());
        $nostoItem->setProductId($item->getProductId() ?? NostoLineItem::PSEUDO_PRODUCT_ID);
        $nostoItem->setQuantity($item->getQuantity());
        $nostoItem->setSkuId($item->getPayload()['productNumber']);

        try {
            $price = $item->getTotalPrice();
            $nostoItem->setPrice($price);
        } catch (Exception) {
            $nostoItem->setPrice(0);
        }

        return $nostoItem;
    }
}
