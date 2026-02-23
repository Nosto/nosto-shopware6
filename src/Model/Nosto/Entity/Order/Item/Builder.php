<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Order\Item;

use Exception;
use Nosto\Model\Cart\LineItem as NostoLineItem;
use Nosto\NostoIntegration\Enums\ProductIdentifierOptions;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
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
        $criteria->addAssociation('children');
        $criteria->addFields([
            'id',
            'parentId',
            'productNumber',
            'active',
            'childCount',
            'displayGroup',
            'isCloseout',
            'availableStock',
            'stock',
            'createdAt',
            'releaseDate',
            'ratingAverage',
            'variantListingConfig',
            'manufacturerNumber',
            'ean',
            'purchaseUnit',
            'referenceUnit',
            'shippingFree',
            'customSearchKeywords',
            'tagIds',
            'streamIds',
            'optionIds',
            'propertyIds',
            'unitId',
            'taxId',
            'price',
            'prices',
            'name',
            'description',
            'customFields',
            'keywords',
            'packUnit',
            'packUnitPlural',
            'metaTitle',
            'metaDescription',
        ]);

        $criteria->addFields([
            'cover.id',
            'cover.media.id',
            'cover.media.url',
            'manufacturer.id',
            'manufacturer.name',
            'manufacturer.media.id',
            'manufacturer.media.url',
            'categoriesRo.id',
            'visibilities.id',
            'visibilities.salesChannelId',
            'visibilities.visibility',
            'media.id',
            'media.position',
            'media.media.id',
            'media.media.url',
        ]);
        $criteria->addFields([
            'children.id',
            'children.parentId',
            'children.productNumber',
            'children.active',
            'children.displayGroup',
            'children.isCloseout',
            'children.availableStock',
            'children.stock',
            'children.releaseDate',
            'children.manufacturerNumber',
            'children.ean',
            'children.shippingFree',
            'children.customSearchKeywords',
            'children.variantListingConfig',
            'children.price',
            'children.prices',
            'children.name',
            'children.customFields',
            'children.optionIds',
            'children.propertyIds',
        ]);
        $criteria->addFields([
            'children.cover.id',
            'children.cover.media.id',
            'children.cover.media.url',
            'children.manufacturer.id',
            'children.manufacturer.name',
            'children.manufacturer.media.id',
            'children.manufacturer.media.url',
            'children.visibilities.id',
            'children.visibilities.salesChannelId',
            'children.visibilities.visibility',
        ]);
        /** @var PartialProduct|null $product */
        $product = $productRepository->search($criteria, $context->getContext())->first();
        if ($product instanceof PartialEntity && !$product instanceof PartialProduct) {
            $product = PartialProductConverter::toPartialProduct($product);
        }

        $criteria = NostoCriteriaFactory::createWithIds([
            $product->getParentId() != null ? $product->getParentId() : $product->getId(),
        ]);
        $criteria->addAssociation('children');
        $criteria->addFields([
            'id',
            'parentId',
            'productNumber',
            'active',
            'childCount',
            'displayGroup',
            'isCloseout',
            'availableStock',
            'stock',
            'createdAt',
            'releaseDate',
            'ratingAverage',
            'variantListingConfig',
            'manufacturerNumber',
            'ean',
            'purchaseUnit',
            'referenceUnit',
            'shippingFree',
            'customSearchKeywords',
            'tagIds',
            'streamIds',
            'optionIds',
            'propertyIds',
            'unitId',
            'taxId',
            'price',
            'prices',
            'name',
            'description',
            'customFields',
            'keywords',
            'packUnit',
            'packUnitPlural',
            'metaTitle',
            'metaDescription',
        ]);

        $criteria->addFields([
            'cover.id',
            'cover.media.id',
            'cover.media.url',
            'manufacturer.id',
            'manufacturer.name',
            'manufacturer.media.id',
            'manufacturer.media.url',
            'categoriesRo.id',
            'visibilities.id',
            'visibilities.salesChannelId',
            'visibilities.visibility',
            'media.id',
            'media.position',
            'media.media.id',
            'media.media.url',
        ]);
        $criteria->addFields([
            'children.id',
            'children.parentId',
            'children.productNumber',
            'children.active',
            'children.displayGroup',
            'children.isCloseout',
            'children.availableStock',
            'children.stock',
            'children.releaseDate',
            'children.manufacturerNumber',
            'children.ean',
            'children.shippingFree',
            'children.customSearchKeywords',
            'children.variantListingConfig',
            'children.price',
            'children.prices',
            'children.name',
            'children.customFields',
            'children.optionIds',
            'children.propertyIds',
        ]);
        $criteria->addFields([
            'children.cover.id',
            'children.cover.media.id',
            'children.cover.media.url',
            'children.manufacturer.id',
            'children.manufacturer.name',
            'children.manufacturer.media.id',
            'children.manufacturer.media.url',
            'children.visibilities.id',
            'children.visibilities.salesChannelId',
            'children.visibilities.visibility',
        ]);
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
