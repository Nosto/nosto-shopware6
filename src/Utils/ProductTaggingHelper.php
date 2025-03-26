<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Utils;

use Nosto\NostoIntegration\Decorator\Core\Content\Product\DataAbstractionLayer\VariantListingConfig;
use Nosto\NostoIntegration\Enums\ProductIdentifierOptions;
use Nosto\NostoIntegration\Enums\StockFieldOptions;
use Nosto\NostoIntegration\Model\ConfigProvider;
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
    ) {
    }

    public function findProductId(
        SalesChannelContext $context,
        ProductEntity $product,
        ProductEntity $variant,
    ): string {
        $variantConfig = $product->getVariantListingConfig();
        $productToReturn = $product;
        $configuratorGroups = array_filter(
            $variantConfig?->getConfiguratorGroupConfig() ?? [],
            static fn (array $config) => $config['expressionForListings'],
        );
        if (!$product->getChildCount() || !($variantConfig instanceof VariantListingConfig)) {
            return $this->getIdOrProductNumber($context, $product);
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
        return $this->getIdOrProductNumber($context, $productToReturn != null ? $productToReturn : $product);
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
        $stock = $this->getProductStock($product, $salesChannelContext);
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

    private function getProductStock(ProductEntity $product, SalesChannelContext $context): int
    {
        if ($this->configProvider->getStockField(
            $context->getSalesChannelId(),
            $context->getLanguageId(),
        ) === StockFieldOptions::ACTUAL_STOCK) {
            return $product->getAvailableStock();
        }
        return $product->getStock();
    }

    private function handleFirstActiveVariant(ProductEntity $product): ?ProductEntity
    {
        $mainProduct = null;
        foreach ($product->getChildren() as $child) {
            if ($child->getActive() && !$mainProduct) {
                $mainProduct = $child;
            }
        }

        return $mainProduct;
    }

    private function handleConfiguratorGroups(
        ProductEntity $product,
        ProductEntity $variantFromDb,
        SalesChannelContext $context,
        bool $hideProductsAfterClearance,
    ): ?ProductEntity {
        $groupedVariants = [];
        foreach ($product->getChildren() as $child) {
            $groupedVariants[$child->getDisplayGroup()][$child->getId()] = $child;
        }
        if ($variantFromDb->getDisplayGroup() !== null) {
            return $this->handleVariantByProperty(
                $groupedVariants[$variantFromDb->getDisplayGroup()],
                $context,
                $hideProductsAfterClearance,
            );
        }

        return null;
    }

    private function handleVariantByProperty(
        array $variants,
        SalesChannelContext $context,
        bool $hideProductsAfterClearance,
    ): ?ProductEntity {
        $mainProduct = null;

        $shouldHandleFirstAvailable = $hideProductsAfterClearance
            && $this->configProvider->isEnabledSyncFirstAvailableVariant();
        foreach ($variants as $child) {
            if ($mainProduct) {
                break;
            }

            if ($shouldHandleFirstAvailable) {
                $stock = $this->getProductStock($child, $context);

                if ($child->getActive() && ($stock > 0 || !$child->getIsCloseout())) {
                    $mainProduct = $child;
                }
            } elseif ($child->getActive()) {
                $mainProduct = $child;
            }
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

        $shouldHandleFirstAvailable = $hideProductsAfterClearance
            && $this->configProvider->isEnabledSyncFirstAvailableVariant();
        foreach ($product->getChildren() as $child) {
            if ($child->getId() === $variantConfig->getMainVariantId()) {
                if ($child->getActive()) {
                    $stock = $this->getProductStock($child, $context);
                    if ($shouldHandleFirstAvailable && ($stock < 1 && $child->getIsCloseout())) {
                        $mainProduct = $this->handleFirstAvailableVariant($product, $context);
                    } else {
                        $mainProduct = $child;
                    }
                } else {
                    $mainProduct = $shouldHandleFirstAvailable
                        ? $this->handleFirstAvailableVariant($product, $context)
                        : $this->handleFirstActiveVariant($product);
                }
            }
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
        foreach ($product->getChildren() as $child) {
            if ($mainProduct) {
                break;
            }
            $stock = $this->getProductStock($child, $context);
            if ($child->getActive() && ($stock > 0 || !$child->getIsCloseout())) {
                $mainProduct = $child;
            }
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

            if ((is_null($lowestPrice) || $variantPrice < $lowestPrice) && $child->getActive()) {
                $lowestPrice = $variantPrice;
                $cheapestVariant = $child;
            }
        }

        return $cheapestVariant;
    }
}
