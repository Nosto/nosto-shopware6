<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Utils;

use Nosto\NostoIntegration\Decorator\Core\Content\Product\DataAbstractionLayer\VariantListingConfig;
use Nosto\NostoIntegration\Enums\ProductIdentifierOptions;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Utils\ProductTaggingHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class ProductTaggingHelperTest extends TestCase
{
    public function testDisplayParentKeepsActiveParentWhenItIsInStock(): void
    {
        $context = $this->createContext();
        $helper = $this->createHelper(false, false);

        $parent = $this->createProduct(
            id: 'parent-id',
            active: true,
            stock: 5,
            closeout: false,
            children: new ProductCollection([
                $this->createProduct(id: 'child-id-1', active: true, stock: 0, closeout: false),
                $this->createProduct(id: 'child-id-2', active: true, stock: 3, closeout: false),
            ]),
            variantListingConfig: new VariantListingConfig(true, null, null, false, false),
        );

        $result = $helper->findProductId($context, $parent, null, false, true);

        self::assertInstanceOf(ProductCollection::class, $result);
        self::assertSame('parent-id', $result->first()?->getId());
    }

    public function testDisplayParentFallsBackToFirstActiveVariantWhenParentIsInactive(): void
    {
        $context = $this->createContext();
        $helper = $this->createHelper(false, false);

        $parent = $this->createProduct(
            id: 'parent-id',
            active: false,
            stock: 0,
            closeout: false,
            children: new ProductCollection([
                $this->createProduct(id: 'child-id-1', active: false, stock: 2, closeout: false),
                $this->createProduct(id: 'child-id-2', active: true, stock: 0, closeout: false),
            ]),
            variantListingConfig: new VariantListingConfig(true, null, null, false, false),
        );

        $result = $helper->findProductId($context, $parent, null, false, true);

        self::assertInstanceOf(ProductCollection::class, $result);
        self::assertSame('child-id-2', $result->first()?->getId());
    }

    public function testDisplayCheapestVariantSelectsCheapestActiveInStockVariant(): void
    {
        $context = $this->createContext();
        $helper = $this->createHelper(false, false);

        $parent = $this->createProduct(
            id: 'parent-id',
            active: true,
            stock: 10,
            closeout: false,
            children: new ProductCollection([
                $this->createProduct(
                    id: 'child-id-1',
                    active: true,
                    stock: 3,
                    closeout: false,
                    priceCollection: $this->createPriceCollection('currency-id', 20.0, 20.0),
                ),
                $this->createProduct(
                    id: 'child-id-2',
                    active: true,
                    stock: 4,
                    closeout: false,
                    priceCollection: $this->createPriceCollection('currency-id', 10.0, 10.0),
                ),
                $this->createProduct(
                    id: 'child-id-3',
                    active: false,
                    stock: 7,
                    closeout: false,
                    priceCollection: $this->createPriceCollection('currency-id', 5.0, 5.0),
                ),
            ]),
            variantListingConfig: new VariantListingConfig(false, null, null, true, false),
        );

        $result = $helper->findProductId($context, $parent, null, false, true);

        self::assertInstanceOf(ProductCollection::class, $result);
        self::assertSame('child-id-2', $result->first()?->getId());
    }

    public function testReturnsOriginalProductWhenNoVariantConfigExists(): void
    {
        $context = $this->createContext();
        $helper = $this->createHelper(false, false);

        $product = $this->createProduct(
            id: 'product-id',
            active: true,
            stock: 5,
            closeout: false,
            productNumber: 'product-number',
        );

        $result = $helper->findProductId($context, $product, null, false, true);

        self::assertSame('product-number', $result);
    }

    public function testReturnsConfiguredMainVariantForProductSync(): void
    {
        $context = $this->createContext();
        $helper = $this->createHelper(false, false);

        $configuredMainVariant = $this->createProduct(
            id: 'child-id',
            active: true,
            stock: 5,
            closeout: false,
            priceCollection: $this->createPriceCollection('currency-id', 10.0, 10.0),
        );

        $parent = $this->createProduct(
            id: 'parent-id',
            active: true,
            stock: 5,
            closeout: false,
            children: new ProductCollection([
                $configuredMainVariant,
            ]),
            variantListingConfig: new VariantListingConfig(false, 'child-id', null, false, false),
        );

        $result = $helper->findProductId($context, $parent, null, false, true);

        self::assertInstanceOf(ProductCollection::class, $result);
        self::assertCount(1, $result);
        $mainProduct = $result->first();
        self::assertNotNull($mainProduct);
        self::assertSame('child-id', $mainProduct->getId());
        self::assertNotNull($mainProduct->getChildren());
        self::assertCount(1, $mainProduct->getChildren());
        self::assertSame('parent-id', $mainProduct->getChildren()->first()?->getId());
    }

    public function testReturnsParentProductWhenCloseoutProductsRemainVisibleEvenIfFirstAvailableSyncIsEnabled(): void
    {
        $context = $this->createContext();
        $helper = $this->createHelper(false, true);

        $parent = $this->createProduct(
            id: 'parent-id',
            active: true,
            stock: 0,
            closeout: true,
            children: new ProductCollection([
                $this->createProduct(id: 'available-child-id', active: true, stock: 5, closeout: false),
            ]),
            variantListingConfig: new VariantListingConfig(true, null, null, false, false),
        );

        $result = $helper->findProductId($context, $parent, null, false, true);

        self::assertInstanceOf(ProductCollection::class, $result);
        self::assertCount(1, $result);
        self::assertSame('parent-id', $result->first()?->getId());
    }

    public function testFirstAvailableVariantIsUsedForClearanceProductsWhenEnabled(): void
    {
        $context = $this->createContext();
        $helper = $this->createHelper(true, true);

        $parent = $this->createProduct(
            id: 'parent-id',
            active: true,
            stock: 0,
            closeout: true,
            children: new ProductCollection([
                $this->createProduct(id: 'child-id-1', active: true, stock: 0, closeout: true),
                $this->createProduct(id: 'child-id-2', active: false, stock: 10, closeout: false),
                $this->createProduct(id: 'child-id-3', active: true, stock: 0, closeout: false),
            ]),
            variantListingConfig: new VariantListingConfig(true, null, null, false, false),
        );

        $result = $helper->findProductId($context, $parent, null, false, true);

        self::assertInstanceOf(ProductCollection::class, $result);
        self::assertSame('child-id-3', $result->first()?->getId());
    }

    public function testReturnsOneVariantPerConfiguratorGroupWhenNoVariantIsPreselected(): void
    {
        $context = $this->createContext();
        $helper = $this->createHelper(false, false);

        $groupAFirst = $this->createProduct(
            id: 'group-a-first-id',
            active: true,
            stock: 5,
            closeout: false,
            displayGroup: 'group-a',
        );
        $groupASecond = $this->createProduct(
            id: 'group-a-second-id',
            active: true,
            stock: 5,
            closeout: false,
            displayGroup: 'group-a',
        );
        $groupBFirst = $this->createProduct(
            id: 'group-b-first-id',
            active: true,
            stock: 5,
            closeout: false,
            displayGroup: 'group-b',
        );
        $parent = $this->createProduct(
            id: 'parent-id',
            active: true,
            stock: 5,
            closeout: false,
            children: new ProductCollection([$groupAFirst, $groupASecond, $groupBFirst]),
            variantListingConfig: new VariantListingConfig(
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
        );

        $result = $helper->findProductId($context, $parent, null, false, true);

        self::assertInstanceOf(ProductCollection::class, $result);
        self::assertCount(2, $result);
        self::assertSame(['group-a-first-id', 'group-b-first-id'], array_values(array_map(
            static fn (ProductEntity $product): ?string => $product->getId(),
            $result->getElements(),
        )));
    }

    public function testReturnsFirstAvailableVariantWhenSelectedMainVariantIsInactiveAndFirstAvailableSyncIsEnabled(): void
    {
        $context = $this->createContext();
        $helper = $this->createHelper(false, true);

        $selectedVariant = $this->createProduct(
            id: 'selected-id',
            active: false,
            stock: 0,
            closeout: false,
        );
        $availableVariant = $this->createProduct(
            id: 'available-id',
            active: true,
            stock: 0,
            closeout: false,
        );
        $parent = $this->createProduct(
            id: 'parent-id',
            active: true,
            stock: 5,
            closeout: false,
            children: new ProductCollection([$selectedVariant, $availableVariant]),
            variantListingConfig: new VariantListingConfig(false, 'selected-id', null, false, false),
        );

        $result = $helper->findProductId($context, $parent, $selectedVariant, false, true);

        self::assertInstanceOf(ProductCollection::class, $result);
        self::assertCount(1, $result);
        self::assertSame('available-id', $result->first()?->getId());
    }

    private function createHelper(
        bool $hideProductsAfterClearance,
        bool $syncFirstAvailableVariant,
    ): ProductTaggingHelper {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('getBool')->willReturnCallback(
            static function (
                string $key,
                ?string $channelId = null,
                ?string $languageId = null,
            ) use ($hideProductsAfterClearance): bool {
                if ($key === 'core.listing.hideCloseoutProductsWhenOutOfStock') {
                    return $hideProductsAfterClearance;
                }

                return false;
            },
        );

        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('getProductIdentifier')->willReturn(ProductIdentifierOptions::PRODUCT_NUMBER);
        $configProvider->method('isEnabledSyncFirstAvailableVariant')->willReturn($syncFirstAvailableVariant);

        $productHelper = $this->createMock(ProductHelper::class);
        $productHelper->method('getProductStock')->willReturnCallback(
            static fn (ProductEntity $product, SalesChannelContext $context): int => $product->getStock(),
        );

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
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        return $context;
    }

    private function createProduct(
        string $id,
        bool $active,
        int $stock,
        bool $closeout,
        ?ProductCollection $children = null,
        ?VariantListingConfig $variantListingConfig = null,
        ?PriceCollection $priceCollection = null,
        ?string $displayGroup = null,
        ?string $productNumber = null,
    ): ProductEntity {
        $product = new ProductEntity();
        $product->setId($id);
        $product->setProductNumber($productNumber ?? $id);
        $product->setActive($active);
        $product->setStock($stock);
        $product->setIsCloseout($closeout);
        $product->setChildCount($children?->count() ?? 0);
        $product->setChildren($children ?? new ProductCollection());
        $product->setVariantListingConfig($variantListingConfig);
        if ($displayGroup !== null) {
            $product->setDisplayGroup($displayGroup);
        }

        if ($priceCollection !== null) {
            $product->setPrice($priceCollection);
        }

        return $product;
    }

    private function createPriceCollection(
        string $currencyId,
        float $net,
        float $gross,
    ): PriceCollection {
        $collection = new PriceCollection();
        $collection->add(new Price($currencyId, $net, $gross, true));

        return $collection;
    }
}
