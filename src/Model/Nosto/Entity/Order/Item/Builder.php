<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Order\Item;

use Exception;
use Nosto\Model\Cart\LineItem as NostoLineItem;
use Nosto\NostoIntegration\Enums\ProductIdentifierOptions;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Currency\CurrencyEntity;

class Builder
{
    public function build(
        OrderLineItemEntity $item,
        CurrencyEntity $currency,
        EntityRepository $productRepository,
        Context $context,
        ProductIdentifierOptions $productIdentifierOptions
    ): NostoLineItem {
        /** @var ProductEntity|null $product */
        $product = $productRepository->search(new Criteria([$item->getProductId()]), $context)->first();
        /** @var ProductEntity|null $parentProduct */
        $parentProduct = $productRepository->search(new Criteria([$product->getParentId()]), $context)->first();
        $skuId = $item->getPayload()['productNumber'];
        if ($productIdentifierOptions === ProductIdentifierOptions::PRODUCT_ID) {
            $skuId = $item->getProductId();
        }
        if ($parentProduct !== null) {
            $productId = $this->getProductId($parentProduct, $productIdentifierOptions);
        } else {
            $productId = $this->getProductId($product, $productIdentifierOptions);
        }
        $nostoItem = new NostoLineItem();
        $nostoItem->setPriceCurrencyCode($currency->getIsoCode());
        $nostoItem->setProductId($productId);
        $nostoItem->setQuantity($item->getQuantity());
        $nostoItem->setSkuId($skuId);
        $nostoItem->setName($product->getTranslation("name"));

        try {
            $price = $item->getUnitPrice();
            $nostoItem->setPrice($price);
        } catch (Exception) {
            $nostoItem->setPrice(0);
        }

        return $nostoItem;
    }

    public function getProductId(
        ProductEntity $productEntity,
        ProductIdentifierOptions $productIdentifierOptions
    ): string|null {
        if ($productIdentifierOptions === ProductIdentifierOptions::PRODUCT_NUMBER) {
            return $productEntity->getProductNumber();
        } else {
            return $productEntity->getId();
        }
    }
}
