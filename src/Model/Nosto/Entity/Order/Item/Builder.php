<?php declare(strict_types=1);

namespace Od\NostoIntegration\Model\Nosto\Entity\Order\Item;

use Exception;
use Nosto\Model\Cart\LineItem as NostoLineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

class Builder
{
    public function build(OrderLineItemEntity $item): NostoLineItem
    {
        $order = $item->getOrder();
        $nostoItem = new NostoLineItem();
        $nostoItem->setPriceCurrencyCode($order->getCurrency()->getIsoCode());
        $nostoItem->setProductId($this->buildItemProductId($item));
        $nostoItem->setQuantity($item->getQuantity());
        $nostoItem->setSkuId($item->getPayload()['manufacturerId']);
        try {
            $price = $item->getTotalPrice();
            $nostoItem->setPrice($price);
        } catch (Exception $e) {
            $nostoItem->setPrice(0);
        }

        return $nostoItem;
    }

    public function buildItemProductId(OrderLineItemEntity $item): string
    {
        $productId = $item->getProductId();
        if (!$productId) {
            return NostoLineItem::PSEUDO_PRODUCT_ID;
        }

        return (string)$productId;
    }
}