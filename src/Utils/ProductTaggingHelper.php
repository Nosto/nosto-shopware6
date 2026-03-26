<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Utils;

use Nosto\Model\Product\Product as NostoProduct;
use Nosto\NostoIntegration\Decorator\Core\Content\Product\DataAbstractionLayer\VariantListingConfig;
use Nosto\NostoIntegration\Enums\ProductIdentifierOptions;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProduct;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProductCollection;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProductConverter;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProvider;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
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
        private readonly PartialProvider|null $partialProductProvider = null,
        private readonly ?ProductHelper $productHelper = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function findProductId(
        SalesChannelContext $context,
        PartialProduct $product,
        ?PartialProduct $variant,
        bool $isProductTagging = false,
        bool $isProductSync = false,
    ): string|NostoProduct|EntityCollection|PartialProductCollection {
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
                $this->applyHandledChildrenForTagging($shopwareProduct, $context, $productToReturn, $product);
                return $this->partialProductProvider->get($shopwareProduct, $context);
            } else {
                return $this->productHelper->getShopwareProductsPartial([$product->getId()], $context);
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
            if ($productToReturn instanceof PartialProductCollection) {
                $mainProducts = $productToReturn;
            } else {
                $mainProducts = new PartialProductCollection();
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
            $this->applyHandledChildrenForTagging($shopwareProduct, $context, $productToReturn, $product);
            return $this->partialProductProvider->get($shopwareProduct, $context);
        }
    }

    private function applyHandledChildrenForTagging(
        object|null $shopwareProduct,
        SalesChannelContext $context,
        ?PartialProduct $handledProduct,
        PartialProduct $fallbackProduct,
    ): void {
        if (!$shopwareProduct || !method_exists($shopwareProduct, 'set')) {
            return;
        }

        $handledChildren = $handledProduct?->getChildren();
        if ($handledChildren === null) {
            $handledChildren = $fallbackProduct->getChildren();
        }

        if ($handledChildren === null) {
            return;
        }

        if ($handledChildren->count() === 0) {
            $shopwareProduct->set('children', new PartialProductCollection());
            return;
        }

        $childrenLoaded = $this->productHelper->getShopwareProductsPartial(
            $handledChildren->getIds(),
            $context,
            false,
        );
        $shopwareProduct->set('children', $childrenLoaded);
    }

    private function handleVariant(
        PartialProduct $product,
        SalesChannelContext $context,
        bool $hideProductsAfterClearance,
    ): ?PartialProduct {
        $mainProduct = null;
        if ($hideProductsAfterClearance && $this->configProvider->isEnabledSyncFirstAvailableVariant()) {
            $mainProduct = $this->handleFirstAvailableVariant($product, $context);
        }

        if (!$mainProduct) {
            $mainProduct = $this->handleFirstActiveVariant($product, $context);
        }
        return $mainProduct;
    }

    private function handleMainProduct(
        PartialProduct $product,
        SalesChannelContext $salesChannelContext,
        bool $hideProductsAfterClearance,
    ): ?PartialProduct {
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
                : $this->handleFirstActiveVariant($product, $salesChannelContext);
        }
        return $mainProduct;
    }

    private function handleFirstActiveVariant(
        PartialProduct $product,
        SalesChannelContext $context,
    ): ?PartialProduct {
        $mainProduct = null;
        $variants = new PartialProductCollection([$product]);
        $children = $this->ensureChildrenLoaded($product, $context);
        if (!$children) {
            return null;
        }

        foreach ($children as $child) {
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
        PartialProduct $product,
        ?PartialProduct $variantFromDb,
        SalesChannelContext $context,
        bool $hideProductsAfterClearance,
    ): null|PartialProduct|PartialProductCollection {
        $groupedVariants = [];
        $children = $this->ensureChildrenLoaded($product, $context);
        if (!$children) {
            return null;
        }

        foreach ($children as $child) {
            $groupedVariants[$child->getDisplayGroup()][$child->getId()] = $child;
        }
        if ($variantFromDb?->getDisplayGroup() !== null) {
            return $this->handleVariantByProperty(
                $groupedVariants[$variantFromDb->getDisplayGroup()],
                $context,
                $hideProductsAfterClearance,
            );
        } else {
            $mainProducts = new PartialProductCollection();
            foreach ($groupedVariants as $variants) {
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
    ): PartialProduct {
        $mainProduct = null;
        $children = new PartialProductCollection();
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

        if (!$mainProduct) {
            foreach ($variants as $candidate) {
                $mainProduct = $candidate;
                break;
            }
            if (!$mainProduct instanceof PartialProduct) {
                throw new \RuntimeException('Expected at least one variant.');
            }

            $children = new PartialProductCollection();
            foreach ($variants as $child) {
                if ($child->getId() !== $mainProduct->getId()) {
                    $children->add($child);
                }
            }
        }

        $mainProduct->setChildren($children);

        return $mainProduct;
    }

    private function handleMainVariant(
        PartialProduct $product,
        VariantListingConfig $variantConfig,
        SalesChannelContext $context,
        bool $hideProductsAfterClearance,
    ): ?PartialProduct {
        $mainProduct = null;
        $variants = new PartialProductCollection([$product]);
        $shouldHandleFirstAvailable = $hideProductsAfterClearance
            && $this->configProvider->isEnabledSyncFirstAvailableVariant();
        $children = $this->ensureChildrenLoaded($product, $context);

        if (!$children) {
            return null;
        }

        foreach ($children as $child) {
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
                    : $this->handleFirstActiveVariant($product, $context);
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
        ProductEntity|PartialProduct $product,
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
        PartialProduct $product,
        SalesChannelContext $context,
    ): ?PartialProduct {
        $mainProduct = null;
        $variants = new PartialProductCollection([$product]);
        $children = $this->ensureChildrenLoaded($product, $context);

        if (!$children) {
            return null;
        }

        foreach ($children as $child) {
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
        PartialProduct $product,
        SalesChannelContext $context,
    ): PartialProduct {
        $cheapestVariant = $product;
        $lowestPrice = null;
        $children = $this->ensureChildrenLoaded($product, $context);

        if (!$children) {
            $cheapestVariant->setChildren(new PartialProductCollection());
            return $cheapestVariant;
        }

        foreach ($children as $child) {
            $currencyPrice = $child->getCurrencyPrice($context->getCurrencyId());
            if ($currencyPrice === null) {
                continue;
            }
            $variantPrice = $currencyPrice->getNet();

            if ((is_null(
                $lowestPrice,
            ) || $variantPrice < $lowestPrice) && $child->getActive() && $child->getStock() > 0) {
                $lowestPrice = $variantPrice;
                $cheapestVariant = $child;
            }
        }

        $cheapestVariant->setChildren(
            $children->filter(
                static fn (PartialProduct $child): bool => $child->getId() !== $cheapestVariant->getId(),
            ),
        );

        return $cheapestVariant;
    }

    private function ensureChildrenLoaded(
        PartialProduct $product,
        SalesChannelContext $context,
    ): ?PartialProductCollection {
        $children = $product->getChildren();
        if ($children) {
            return $children;
        }

        if (($product->getChildCount() ?? 0) < 1 || !$this->productHelper) {
            return null;
        }

        $productId = $product->getId();
        if (!$productId) {
            return null;
        }

        $startedAt = microtime(true);

        $loadedProduct = PartialProductConverter::toPartialProductCollection(
            $this->productHelper->getShopwareProductsPartial([$productId], $context, true),
        )->first();
        if (!$loadedProduct instanceof PartialProduct) {
            return null;
        }

        $loadedProductChildren = $loadedProduct->getChildren();
        if ($loadedProductChildren) {
            $product->setChildren($loadedProductChildren);
        }

        $this->logDuration(
            $context,
            'product_sync.productTaggingHelper.ensureChildrenLoaded',
            $startedAt,
            [
                'product_id' => $productId,
                'child_count' => $product->getChildCount(),
                'loaded_count' => $loadedProductChildren?->count() ?? 0,
            ],
        );

        return $loadedProductChildren;
    }

    private function logDuration(
        SalesChannelContext $context,
        string $message,
        float $startTime,
        array $additionalContext = [],
    ): void {
        if (!$this->logger) {
            return;
        }

        $durationMs = (microtime(true) - $startTime) * 1000;
        $this->logger->info($message, array_merge(
            $additionalContext,
            [
                'duration_ms' => round($durationMs, 2),
                'sales_channel_id' => $context->getSalesChannelId(),
                'language_id' => $context->getLanguageId(),
            ],
        ));
    }
}
