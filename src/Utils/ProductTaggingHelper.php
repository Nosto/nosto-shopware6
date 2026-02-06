<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Utils;

use Nosto\Model\Product\Product as NostoProduct;
use Nosto\NostoIntegration\Decorator\Core\Content\Product\DataAbstractionLayer\VariantListingConfig;
use Nosto\NostoIntegration\Enums\ProductIdentifierOptions;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\ProductProviderInterface;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProvider;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Helper class to find the product id that is used in Nosto for the main product.
 * If you are changing logic here!
 * make sure you also change/check the logic on here {@see \Nosto\NostoIntegration\Model\Operation\ProductSyncHandler}
 * at function processProductVariants
 */
class ProductTaggingHelper
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly ConfigProvider $configProvider,
        private readonly ?ProductProviderInterface $productProvider = null,
        private readonly ?ProductHelper $productHelper = null,
        private readonly ?PartialProvider $partialProductProvider = null,
    ) {
    }

    public function findProductId(
        SalesChannelContext $context,
        ProductEntity $product,
        ?ProductEntity $variant,
        bool $isProductTagging = false,
        bool $isProductSync = false,
    ): string|NostoProduct|ProductCollection {
        $variantConfig = $product->getVariantListingConfig();
        $productToReturn = $product;
        $configuratorGroups = array_filter(
            $variantConfig?->getConfiguratorGroupConfig() ?? [],
            static fn (array $config) => $config['expressionForListings'],
        );
        if (!$product->getChildCount() || !($variantConfig instanceof VariantListingConfig)) {
            if (!$isProductTagging) {
                return $this->getIdOrProductNumber($context, $product);
            } elseif (!$isProductSync) {
                $shopwareProduct = $this->productHelper->getShopwareProductsPartial(
                    [$product->getId()],
                    $context,
                )->first();
                $handledChildren = $productToReturn->getChildren();
                if (
                    ($handledChildren === null || $handledChildren->count() === 0) &&
                    $product->getChildren() &&
                    $product->getChildren()->count() > 0
                ) {
                    $handledChildren = $product->getChildren();
                }
                if ($shopwareProduct && $handledChildren && $handledChildren->count() && method_exists($shopwareProduct, 'set')) {
                    $childrenLoaded = $this->productHelper->getShopwareProductsPartial(
                        $handledChildren->getIds(),
                        $context,
                        false,
                    );
                    $shopwareProduct->set('children', $childrenLoaded);
                }
                return $this->partialProductProvider->get($shopwareProduct, $context);
            } else {
                return new ProductCollection([$product]);
            }
        }
        $hideProductsAfterClearance = $this->systemConfigService->getBool(
            'core.listing.hideCloseoutProductsWhenOutOfStock',
            $context->getSalesChannelId(),
        );

        if ($variantConfig->getDisplayParent()) {
            $productToReturn = $this->handleMainProduct($product, $context, $hideProductsAfterClearance);
        } elseif ($variantConfig->getDisplayCheapestVariant()) {
            $productToReturn = $this->handleCheapestVariant($product, $context);
        } elseif ($variantConfig->getMainVariantId()) {
            if ($variant = $this->handleMainVariant($product, $variantConfig, $context, $hideProductsAfterClearance)) {
                $productToReturn = $variant;
            }
        } elseif (count($configuratorGroups)) {
            $productToReturn = $this->handleConfiguratorGroups(
                $product,
                $variant,
                $context,
                $hideProductsAfterClearance,
            );
        } else {
            if ($variant = $this->handleVariant($product, $context, $hideProductsAfterClearance)) {
                $productToReturn = $variant;
            }
        }
        //if for whatever reason we don't find the correct product return just main product id
        if ($isProductSync) {
            if ($productToReturn instanceof ProductCollection) {
                $mainProducts = $productToReturn;
            } else {
                $mainProducts = new ProductCollection();
                $mainProducts->add($productToReturn);
            }
            return $mainProducts;
        } elseif (!$isProductTagging) {
            return $this->getIdOrProductNumber($context, $productToReturn != null ? $productToReturn : $product);
        } else {
            $productId = $productToReturn?->getId() ?? $product->getId();
            $shopwareProduct = $this->productHelper->getShopwareProductsPartial(
                [$productId],
                $context,
            )->first();
            $handledChildren = $productToReturn?->getChildren() ?? $product->getChildren();
            if (
                ($handledChildren === null || $handledChildren->count() === 0) &&
                $product->getChildren() &&
                $product->getChildren()->count() > 0
            ) {
                $handledChildren = $product->getChildren();
            }
            if ($shopwareProduct && $handledChildren && $handledChildren->count() && method_exists($shopwareProduct, 'set')) {
                $childrenLoaded = $this->productHelper->getShopwareProductsPartial(
                    $handledChildren->getIds(),
                    $context,
                    false,
                );
                $shopwareProduct->set('children', $childrenLoaded);
            }
            return $this->partialProductProvider->get($shopwareProduct, $context);
        }
    }

    private function handleVariant(
        ProductEntity $product,
        SalesChannelContext $context,
        bool $hideProductsAfterClearance,
    ): ?ProductEntity {
        $mainProduct = null;
        if ($hideProductsAfterClearance && $this->configProvider->isEnabledSyncFirstAvailableVariant()) {
            $mainProduct = $this->handleFirstAvailableVariant($product, $context);
        }

        if (!$mainProduct) {
            $mainProduct = $this->handleFirstActiveVariant($product);
        }
        return $mainProduct;
    }

    private function handleMainProduct(
        ProductEntity $product,
        SalesChannelContext $salesChannelContext,
        bool $hideProductsAfterClearance,
    ): ?ProductEntity {
        $stock = $this->productHelper->getProductStock($product, $salesChannelContext);
        $shouldHandleFirstAvailable = $hideProductsAfterClearance
            && $this->configProvider->isEnabledSyncFirstAvailableVariant();
        if ($product->getActive()) {
            if ($shouldHandleFirstAvailable && ($stock < 1 && $product->getIsCloseout())) {
                $mainProduct = $this->handleFirstAvailableVariant($product, $salesChannelContext);
            } else {
                $mainProduct = $product;
            }
        } else {
            $mainProduct = $shouldHandleFirstAvailable
                ? $this->handleFirstAvailableVariant($product, $salesChannelContext)
                : $this->handleFirstActiveVariant($product);
        }
        return $mainProduct;
    }

    private function handleFirstActiveVariant(ProductEntity $product): ?ProductEntity
    {
        $mainProduct = null;
        $variants = new ProductCollection([$product]);

        foreach ($product->getChildren() as $child) {
            if ($child->getActive() && !$mainProduct) {
                $mainProduct = $child;
            } else {
                $variants->add($child);
            }
        }

        if ($mainProduct) {
            $mainProduct->setChildren($variants);
        }

        return $mainProduct;
    }

    private function handleConfiguratorGroups(
        ProductEntity $product,
        ?ProductEntity $variantFromDb,
        SalesChannelContext $context,
        bool $hideProductsAfterClearance,
    ): null|ProductEntity|ProductCollection {
        $groupedVariants = [];
        foreach ($product->getChildren() as $child) {
            $groupedVariants[$child->getDisplayGroup()][$child->getId()] = $child;
        }
        if ($variantFromDb?->getDisplayGroup() !== null) {
            return $this->handleVariantByProperty(
                $groupedVariants[$variantFromDb->getDisplayGroup()],
                $context,
                $hideProductsAfterClearance,
            );
        } else {
            $mainProducts = new ProductCollection();
            foreach ($groupedVariants as $variants) {
                /** @var SalesChannelProductEntity $mainProduct */
                $mainProduct = $this->handleVariantByProperty($variants, $context, $hideProductsAfterClearance);
                if ($mainProduct) {
                    $mainProducts->add($mainProduct);
                }
            }
            return $mainProducts;
        }
    }

    private function handleVariantByProperty(
        array $variants,
        SalesChannelContext $context,
        bool $hideProductsAfterClearance,
    ): ?ProductEntity {
        $mainProduct = null;
        $children = new ProductCollection();
        $shouldHandleFirstAvailable = $hideProductsAfterClearance
            && $this->configProvider->isEnabledSyncFirstAvailableVariant();

        foreach ($variants as $child) {
            if ($shouldHandleFirstAvailable) {
                $stock = $this->productHelper->getProductStock($child, $context);
                if (!$mainProduct && $child->getActive() && ($stock > 0 || !$child->getIsCloseout())) {
                    $mainProduct = $child;
                } else {
                    $children->add($child);
                }
            } elseif (!$mainProduct && $child->getActive()) {
                $mainProduct = $child;
            } else {
                $children->add($child);
            }
        }

        if ($mainProduct) {
            $mainProduct->setChildren($children);
        }

        return $mainProduct;
    }

    private function handleMainVariant(
        ProductEntity $product,
        VariantListingConfig $variantConfig,
        SalesChannelContext $context,
        bool $hideProductsAfterClearance,
    ): ?ProductEntity {
        $mainProduct = null;
        $variants = new ProductCollection([$product]);
        $shouldHandleFirstAvailable = $hideProductsAfterClearance
            && $this->configProvider->isEnabledSyncFirstAvailableVariant();

        foreach ($product->getChildren() as $child) {
            if ($child->getId() !== $variantConfig->getMainVariantId()) {
                $variants->add($child);
                continue;
            }

            if ($child->getActive()) {
                $stock = $this->productHelper->getProductStock($child, $context);
                $mainProduct = $shouldHandleFirstAvailable && $stock < 1 && $child->getIsCloseout()
                    ? $this->handleFirstAvailableVariant($product, $context)
                    : $child;
            } else {
                $mainProduct = $shouldHandleFirstAvailable
                    ? $this->handleFirstAvailableVariant($product, $context)
                    : $this->handleFirstActiveVariant($product);
            }
        }

        if (!$mainProduct) {
            $mainProduct = $this->handleFirstAvailableVariant($product, $context);
        }

        if ($mainProduct && $mainProduct->getId() === $variantConfig->getMainVariantId()) {
            $mainProduct->setChildren($variants);
        }

        return $mainProduct;
    }

    private function getIdOrProductNumber(
        SalesChannelContext $context,
        ProductEntity $product,
    ): string {
        $useProductNumber = $this->configProvider->getProductIdentifier(
                $context->getSalesChannelId(),
                $context->getLanguageId(),
            ) === ProductIdentifierOptions::PRODUCT_NUMBER;
        if ($useProductNumber) {
            return $product->getProductNumber();
        } else {
            return $product->getId();
        }
    }

    private function handleFirstAvailableVariant(
        ProductEntity $product,
        SalesChannelContext $context,
    ): ?ProductEntity {
        $mainProduct = null;
        $variants = new ProductCollection([$product]);

        foreach ($product->getChildren() as $child) {
            $stock = $this->productHelper->getProductStock($child, $context);

            if (!$mainProduct && $child->getActive() && ($stock > 0 || !$child->getIsCloseout())) {
                $mainProduct = $child;
            } else {
                $variants->add($child);
            }
        }

        if ($mainProduct) {
            $mainProduct->setChildren($variants);
        }

        return $mainProduct;
    }

    private function handleCheapestVariant(
        ProductEntity $product,
        SalesChannelContext $context,
    ): ProductEntity {
        $cheapestVariant = $product;
        $lowestPrice = null;

        foreach ($product->getChildren() as $child) {
            $variantPrice = $child->getCurrencyPrice($context->getCurrencyId())->getNet();

            if ((is_null(
                        $lowestPrice,
                    ) || $variantPrice < $lowestPrice) && $child->getActive() && $child->getStock() > 0) {
                $lowestPrice = $variantPrice;
                $cheapestVariant = $child;
            }
        }

        $cheapestVariant->setChildren(
            $product->getChildren()->filter(
                static fn (ProductEntity $child): bool => $child->getId() !== $cheapestVariant->getId(),
            ),
        );

        return $cheapestVariant;
    }
}
