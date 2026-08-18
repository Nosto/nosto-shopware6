<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Utils;

use Nosto\NostoIntegration\Decorator\Core\Content\Product\DataAbstractionLayer\VariantListingConfig;
use Nosto\NostoIntegration\Enums\ProductIdentifierOptions;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProduct;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProductCollection;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProductConverter;
use Nosto\NostoIntegration\Utils\ProductTaggingHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class ProductTaggingHelperTest extends TestCase
{
    public function testReturnsOriginalProductForProductSyncWhenNoVariantConfigExists(): void
    {
        $product = $this->createProduct('product-id', 'product-number');
        $helper = $this->createHelper();
        $context = $this->createContext();

        $result = $helper->findProductId($context, $product, null, false, true);

        self::assertSame('product-number', $result);
    }

    public function testReturnsFirstActiveChildForProductSyncWhenDisplayParentIsEnabledAndParentIsInactive(): void
    {
        $helper = $this->createHelper();
        $context = $this->createContext();
        $child = $this->createProduct('child-id', 'child-number', null, true);
        $parent = $this->createProduct(
            'parent-id',
            'parent-number',
            $this->createVariantConfig(true, null, null),
            false,
            false,
            1,
            new PartialProductCollection([$child]),
        );

        $result = $helper->findProductId($context, $parent, null, false, true);

        self::assertInstanceOf(PartialProductCollection::class, $result);
        self::assertCount(1, $result);
        self::assertSame('child-id', $result->first()?->getId());
    }

    public function testReturnsConfiguredMainVariantForProductSync(): void
    {
        $productHelper = $this->createMock(ProductHelper::class);
        $productHelper->method('getProductStock')->willReturn(5);

        $helper = $this->createHelper(null, $productHelper);
        $context = $this->createContext();
        $child = $this->createProduct('child-id', 'child-number', null, true);
        $parent = $this->createProduct(
            'parent-id',
            'parent-number',
            $this->createVariantConfig(false, 'child-id', null),
            true,
            false,
            1,
            new PartialProductCollection([$child]),
        );

        $result = $helper->findProductId($context, $parent, null, false, true);

        self::assertInstanceOf(PartialProductCollection::class, $result);
        self::assertCount(1, $result);
        $mainProduct = $result->first();
        self::assertNotNull($mainProduct);
        self::assertSame('child-id', $mainProduct->getId());
        self::assertNotNull($mainProduct->getChildren());
        self::assertCount(1, $mainProduct->getChildren());
        self::assertSame('parent-id', $mainProduct->getChildren()->first()?->getId());
    }

    public function testReturnsParentProductWhenDisplayParentIsEnabledAndParentIsActive(): void
    {
        $helper = $this->createHelper();
        $context = $this->createContext();
        $child = $this->createProduct('child-id', 'child-number', null, true, false, 0);
        $parent = $this->createProduct(
            'parent-id',
            'parent-number',
            $this->createVariantConfig(true, null, null),
            true,
            false,
            1,
            new PartialProductCollection([$child]),
        );

        $result = $helper->findProductId($context, $parent, null, false, true);

        self::assertInstanceOf(PartialProductCollection::class, $result);
        self::assertCount(1, $result);
        self::assertSame('parent-id', $result->first()?->getId());
    }

    public function testReturnsFirstAvailableChildWhenDisplayParentIsEnabledAndParentIsOutOfStockCloseout(): void
    {
        $context = $this->createContext();
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->expects($this->once())
            ->method('isEnabledSyncFirstAvailableVariant')
            ->with($context->getSalesChannelId(), $context->getLanguageId())
            ->willReturn(true);

        $helper = $this->createHelper($configProvider, null, true, true);
        $inactiveChild = $this->createProduct('inactive-child-id', 'inactive-child-number', null, false, false, 0);
        $availableChild = $this->createProduct('available-child-id', 'available-child-number', null, true, false, 0);
        $parent = $this->createProduct(
            'parent-id',
            'parent-number',
            $this->createVariantConfig(true, null, null, true),
            true,
            true,
            2,
            new PartialProductCollection([$inactiveChild, $availableChild]),
        );

        $result = $helper->findProductId($context, $parent, null, false, true);

        self::assertInstanceOf(PartialProductCollection::class, $result);
        self::assertCount(1, $result);
        self::assertSame('available-child-id', $result->first()?->getId());
    }

    public function testReturnsParentProductWhenCloseoutProductsRemainVisibleEvenIfFirstAvailableSyncIsEnabled(): void
    {
        $helper = $this->createHelper(null, null, true, false);
        $context = $this->createContext();
        $availableChild = $this->createProduct('available-child-id', 'available-child-number', null, true, false, 0);
        $parent = $this->createProduct(
            'parent-id',
            'parent-number',
            $this->createVariantConfig(true, null, null, true),
            true,
            true,
            2,
            new PartialProductCollection([$availableChild]),
        );

        $result = $helper->findProductId($context, $parent, null, false, true);

        self::assertInstanceOf(PartialProductCollection::class, $result);
        self::assertCount(1, $result);
        self::assertSame('parent-id', $result->first()?->getId());
    }

    public function testReturnsCheapestActiveInStockVariantWhenCheapestVariantIsEnabled(): void
    {
        $helper = $this->createHelper();
        $context = $this->createContext();
        $inactiveCheapest = $this->createProduct(
            'inactive-cheapest-id',
            'inactive-cheapest-number',
            null,
            false,
            false,
            0,
            price: $this->createPriceCollection('currency-id', 1.0, 1.19),
        );
        $outOfStockVariant = $this->createProduct(
            'out-of-stock-id',
            'out-of-stock-number',
            null,
            true,
            false,
            0,
            price: $this->createPriceCollection('currency-id', 2.0, 2.38),
        );
        $cheapestActiveVariant = $this->createProduct(
            'cheapest-active-id',
            'cheapest-active-number',
            null,
            true,
            false,
            0,
            price: $this->createPriceCollection('currency-id', 0.5, 0.59),
            stock: 2,
        );
        $parent = $this->createProduct(
            'parent-id',
            'parent-number',
            $this->createVariantConfig(false, null, null, true),
            true,
            false,
            3,
            new PartialProductCollection([$inactiveCheapest, $outOfStockVariant, $cheapestActiveVariant]),
        );

        $result = $helper->findProductId($context, $parent, null, false, true);

        self::assertInstanceOf(PartialProductCollection::class, $result);
        self::assertCount(1, $result);
        self::assertSame('cheapest-active-id', $result->first()?->getId());
    }

    public function testReturnsFirstActiveVariantWhenSelectedMainVariantIsInactive(): void
    {
        $helper = $this->createHelper();
        $context = $this->createContext();
        $selectedVariant = $this->createProduct('selected-id', 'selected-number', null, false, false, 0);
        $fallbackVariant = $this->createProduct('fallback-id', 'fallback-number', null, true, false, 0);
        $parent = $this->createProduct(
            'parent-id',
            'parent-number',
            $this->createVariantConfig(false, 'selected-id', null, false),
            true,
            false,
            2,
            new PartialProductCollection([$selectedVariant, $fallbackVariant]),
        );

        $result = $helper->findProductId($context, $parent, $selectedVariant, false, true);

        self::assertInstanceOf(PartialProductCollection::class, $result);
        self::assertCount(1, $result);
        self::assertSame('fallback-id', $result->first()?->getId());
    }

    public function testReturnsFirstAvailableVariantWhenSelectedMainVariantIsInactiveAndFirstAvailableSyncIsEnabled(): void
    {
        $helper = $this->createHelper(null, null, true, true);
        $context = $this->createContext();
        $selectedVariant = $this->createProduct('selected-id', 'selected-number', null, false, false, 0);
        $availableVariant = $this->createProduct('available-id', 'available-number', null, true, false, 0);
        $parent = $this->createProduct(
            'parent-id',
            'parent-number',
            $this->createVariantConfig(false, 'selected-id', null, false),
            true,
            false,
            2,
            new PartialProductCollection([$selectedVariant, $availableVariant]),
        );

        $result = $helper->findProductId($context, $parent, $selectedVariant, false, true);

        self::assertInstanceOf(PartialProductCollection::class, $result);
        self::assertCount(1, $result);
        self::assertSame('available-id', $result->first()?->getId());
    }

    public function testReturnsOneVariantPerConfiguratorGroupWhenNoVariantIsPreselected(): void
    {
        $helper = $this->createHelper();
        $context = $this->createContext();
        $groupAFirst = $this->createProduct(
            'group-a-first-id',
            'group-a-first-number',
            null,
            true,
            false,
            0,
            displayGroup: 'group-a',
        );
        $groupASecond = $this->createProduct(
            'group-a-second-id',
            'group-a-second-number',
            null,
            true,
            false,
            0,
            displayGroup: 'group-a',
        );
        $groupBFirst = $this->createProduct(
            'group-b-first-id',
            'group-b-first-number',
            null,
            true,
            false,
            0,
            displayGroup: 'group-b',
        );
        $parent = $this->createProduct(
            'parent-id',
            'parent-number',
            $this->createVariantConfig(
                false,
                null,
                [
                    [
                        'expressionForListings' => true,
                    ],
                    [
                        'expressionForListings' => true,
                    ],
                ],
                false,
                false,
            ),
            true,
            false,
            3,
            new PartialProductCollection([$groupAFirst, $groupASecond, $groupBFirst]),
        );

        $result = $helper->findProductId($context, $parent, null, false, true);

        self::assertInstanceOf(PartialProductCollection::class, $result);
        self::assertCount(2, $result);
        self::assertSame(['group-a-first-id', 'group-b-first-id'], array_values(array_map(
            static fn (PartialProduct $product): ?string => $product->getId(),
            $result->getElements(),
        )));
    }

    public function testReturnsFirstAvailableVariantForSelectedConfiguratorGroupWhenEnabled(): void
    {
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isEnabledSyncFirstAvailableVariant')->willReturn(true);

        $helper = $this->createHelper($configProvider, null, true, true);
        $context = $this->createContext();
        $firstVariant = $this->createProduct(
            'group-a-first-id',
            'group-a-first-number',
            null,
            true,
            true,
            0,
            displayGroup: 'group-a',
        );
        $availableVariant = $this->createProduct(
            'group-a-available-id',
            'group-a-available-number',
            null,
            true,
            false,
            0,
            displayGroup: 'group-a',
        );
        $parent = $this->createProduct(
            'parent-id',
            'parent-number',
            $this->createVariantConfig(
                false,
                null,
                [
                    [
                        'expressionForListings' => true,
                    ],
                ],
                false,
                false,
            ),
            true,
            false,
            2,
            new PartialProductCollection([$firstVariant, $availableVariant]),
        );

        $result = $helper->findProductId($context, $parent, $firstVariant, false, true);

        self::assertInstanceOf(PartialProductCollection::class, $result);
        self::assertCount(1, $result);
        self::assertSame('group-a-available-id', $result->first()?->getId());
    }

    private function createHelper(
        ?ConfigProvider $configProvider = null,
        ?ProductHelper $productHelper = null,
        bool $syncFirstAvailable = false,
        bool $hideCloseoutProductsWhenOutOfStock = false,
    ): ProductTaggingHelper {
        $productHelper ??= $this->createMock(ProductHelper::class);
        $productHelper->method('getProductStock')->willReturn(0);
        $productHelper->method('getShopwareProductsPartial')->willReturn(
            new \Shopware\Core\Content\Product\ProductCollection(),
        );

        if ($configProvider === null) {
            $configProvider = $this->createMock(ConfigProvider::class);
            $configProvider->method('getProductIdentifier')->willReturn(ProductIdentifierOptions::PRODUCT_NUMBER);
            $configProvider->method('isEnabledSyncFirstAvailableVariant')->willReturn($syncFirstAvailable);
        }
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('getBool')->willReturn($hideCloseoutProductsWhenOutOfStock);

        return new ProductTaggingHelper(
            $systemConfigService,
            $configProvider,
            null,
            $productHelper,
        );
    }

    private function createContext(): SalesChannelContext
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sales-channel-id');
        $context->method('getLanguageId')->willReturn('language-id');
        $context->method('getCurrencyId')->willReturn('currency-id');

        return $context;
    }

    private function createProduct(
        string $id,
        string $productNumber,
        ?VariantListingConfig $variantListingConfig = null,
        bool $active = true,
        bool $closeout = false,
        int $childCount = 0,
        ?PartialProductCollection $children = null,
        ?PriceCollection $price = null,
        int $stock = 0,
        ?string $displayGroup = null,
    ): PartialProduct {
        $product = PartialProductConverter::toPartialProduct(new PartialEntity([
            'id' => $id,
            'productNumber' => $productNumber,
            'active' => $active,
            'isCloseout' => $closeout,
            'childCount' => $childCount,
            'stock' => $stock,
        ]));

        if ($variantListingConfig !== null) {
            $product->setVariantListingConfig($variantListingConfig);
        }

        if ($children !== null) {
            $product->setChildren($children);
        }

        if ($price !== null) {
            $product->setPrice($price);
        }

        if ($displayGroup !== null) {
            $product->setDisplayGroup($displayGroup);
        }

        return $product;
    }

    private function createPriceCollection(string $currencyId, float $net, float $gross): PriceCollection
    {
        $price = new Price($currencyId, $net, $gross, true);
        $collection = new PriceCollection();
        $collection->add($price);

        return $collection;
    }

    private function createVariantConfig(
        bool $displayParent,
        ?string $mainVariantId,
        ?array $configuratorGroupConfig,
        bool $displayCheapestVariant = false,
        bool $displayMainVariant = false,
    ): VariantListingConfig {
        return new VariantListingConfig(
            $displayParent,
            $mainVariantId,
            $configuratorGroupConfig,
            $displayCheapestVariant,
            $displayMainVariant,
        );
    }
}
