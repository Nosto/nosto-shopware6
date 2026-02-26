<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Order\Item;

use Exception;
use Nosto\Model\Cart\LineItem as NostoLineItem;
use Nosto\NostoIntegration\Enums\ProductIdentifierOptions;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\ProductFieldSets;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProduct;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProductConverter;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProvider;
use Nosto\NostoIntegration\Utils\NostoCriteriaFactory;
use Nosto\NostoIntegration\Utils\ProductTaggingHelper;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class Builder
{
    public function build(
        OrderLineItemEntity $item,
        CurrencyEntity $currency,
        EntityRepository $productRepository,
        SalesChannelContext $context,
        ConfigProvider $configProvider,
        SystemConfigService $systemConfigService,
        ProductHelper $productHelper,
        PartialProvider $partialProductProvider,
    ): NostoLineItem {
        $criteria = NostoCriteriaFactory::createWithIds([$item->getProductId()]);
        $criteria->addFields(ProductFieldSets::productFieldsWithChildren());

        /** @var PartialProduct|null $product */
        $product = $productRepository->search($criteria, $context->getContext())->first();
        if ($product instanceof PartialEntity && !$product instanceof PartialProduct) {
            $product = PartialProductConverter::toPartialProduct($product);
        }

        $criteria = NostoCriteriaFactory::createWithIds([
            $product->getParentId() != null ? $product->getParentId() : $product->getId(),
        ]);
        $criteria->addFields(ProductFieldSets::productFieldsWithChildren());
        /** @var PartialProduct|null $parentProduct */
        $parentProduct = $productRepository->search($criteria, $context->getContext())->first();
        if ($parentProduct instanceof PartialEntity && !$parentProduct instanceof PartialProduct) {
            $parentProduct = PartialProductConverter::toPartialProduct($parentProduct);
        }
        if (!$product instanceof PartialProduct || !$parentProduct instanceof PartialProduct) {
            throw new Exception('Unable to resolve product for order line item.');
        }
        $skuId = $item->getPayload()['productNumber'];
        if ($configProvider->getProductIdentifier(
            $context->getSalesChannelId(),
            $context->getLanguageId(),
        ) === ProductIdentifierOptions::PRODUCT_ID) {
            $skuId = $item->getProductId();
        }
        $productTaggingHelper = new ProductTaggingHelper(
            $systemConfigService,
            $configProvider,
            $partialProductProvider,
            $productHelper,
        );
        $productId = $productTaggingHelper->findProductId($context, $parentProduct, $product);

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
        ProductIdentifierOptions $productIdentifierOptions,
    ): string|null {
        if ($productIdentifierOptions === ProductIdentifierOptions::PRODUCT_NUMBER) {
            return $productEntity->getProductNumber();
        } else {
            return $productEntity->getId();
        }
    }
}
