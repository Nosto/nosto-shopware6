<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Utils;

use Nosto\Model\Product\Product as NostoProduct;
use Nosto\NostoIntegration\Decorator\Core\Content\Product\DataAbstractionLayer\VariantListingConfig;
use Nosto\NostoIntegration\Enums\ProductIdentifierOptions;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\ProductProviderInterface;
use Nosto\NostoIntegration\Tests\TestCase;
use Nosto\NostoIntegration\Utils\ProductTaggingHelper;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ProductTaggingHelperTest extends TestCase
{
    private ProductTaggingHelper $productTaggingHelper;

    private MockObject|SystemConfigService $systemConfigService;

    private MockObject|ConfigProvider $configProvider;

    private MockObject|ProductProviderInterface $productProvider;

    private MockObject|ProductHelper $productHelper;

    private MockObject|SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->configProvider = $this->createMock(ConfigProvider::class);
        $this->productProvider = $this->createMock(ProductProviderInterface::class);
        $this->productHelper = $this->createMock(ProductHelper::class);
        $this->salesChannelContext = $this->createMock(SalesChannelContext::class);

        $this->productTaggingHelper = new ProductTaggingHelper(
            $this->systemConfigService,
            $this->configProvider,
            $this->productProvider,
            $this->productHelper,
        );

        $this->salesChannelContext->method('getSalesChannelId')
            ->willReturn('test-sales-channel-id');
        $this->salesChannelContext->method('getLanguageId')
            ->willReturn('test-language-id');
        $this->salesChannelContext->method('getCurrencyId')
            ->willReturn('test-currency-id');
    }

    public function testProductTaggingHelperClassExists(): void
    {
        $this->assertTrue(class_exists(ProductTaggingHelper::class));
    }

    public function testConstructorWithOptionalDependencies(): void
    {
        $helper = new ProductTaggingHelper(
            $this->systemConfigService,
            $this->configProvider,
        );

        $this->assertInstanceOf(ProductTaggingHelper::class, $helper);
    }

    public function testFindProductIdForSimpleProductWithoutVariants(): void
    {
        $product = $this->createProductEntity();
        $product->method('getChildCount')->willReturn(0);
        $product->method('getId')->willReturn('product-id-123');
        $product->method('getProductNumber')->willReturn('PROD-123');

        $this->configProvider->expects($this->once())
            ->method('getProductIdentifier')
            ->with('test-sales-channel-id', 'test-language-id')
            ->willReturn(ProductIdentifierOptions::PRODUCT_ID);

        $result = $this->productTaggingHelper->findProductId(
            $this->salesChannelContext,
            $product,
            null,
        );

        $this->assertEquals('product-id-123', $result);
    }

    public function testFindProductIdWithProductNumberIdentifier(): void
    {
        $product = $this->createProductEntity();
        $product->method('getChildCount')->willReturn(0);
        $product->method('getId')->willReturn('product-id-123');
        $product->method('getProductNumber')->willReturn('PROD-123');

        $this->configProvider->expects($this->once())
            ->method('getProductIdentifier')
            ->with('test-sales-channel-id', 'test-language-id')
            ->willReturn(ProductIdentifierOptions::PRODUCT_NUMBER);

        $result = $this->productTaggingHelper->findProductId(
            $this->salesChannelContext,
            $product,
            null,
        );

        $this->assertEquals('PROD-123', $result);
    }

    public function testFindProductIdForProductTagging(): void
    {
        $product = $this->createProductEntity();
        $product->method('getChildCount')->willReturn(0);
        $product->method('getId')->willReturn('product-id-123');

        $shopwareProduct = $this->createMock(SalesChannelProductEntity::class);
        $shopwareProduct->method('setChildren');

        $salesChannelProductCollection = $this->createMock(SalesChannelProductCollection::class);
        $salesChannelProductCollection->method('first')->willReturn($shopwareProduct);

        $this->productHelper->expects($this->once())
            ->method('getShopwareProducts')
            ->with(['product-id-123'], $this->salesChannelContext)
            ->willReturn($salesChannelProductCollection);

        $nostoProduct = $this->createMock(NostoProduct::class);
        $this->productProvider->expects($this->once())
            ->method('get')
            ->with($shopwareProduct, $this->salesChannelContext)
            ->willReturn($nostoProduct);

        $result = $this->productTaggingHelper->findProductId(
            $this->salesChannelContext,
            $product,
            null,
            true,
        );

        $this->assertInstanceOf(NostoProduct::class, $result);
    }

    public function testFindProductIdForProductSync(): void
    {
        $product = $this->createProductEntity();
        $product->method('getChildCount')->willReturn(0);
        $product->method('getId')->willReturn('sync-product-id');
        $product->method('getProductNumber')->willReturn('SYNC-PRODUCT');

        $this->configProvider->method('getProductIdentifier')
            ->with('test-sales-channel-id', 'test-language-id')
            ->willReturn(ProductIdentifierOptions::PRODUCT_ID);

        $result = $this->productTaggingHelper->findProductId(
            $this->salesChannelContext,
            $product,
            null,
            true,
            true,
        );

        $this->assertInstanceOf(ProductCollection::class, $result);
        $this->assertCount(1, $result);
    }

    public function testFindProductIdWithDisplayParentVariantConfig(): void
    {
        $product = $this->createProductEntity();
        $product->method('getChildCount')->willReturn(2);

        $variantConfig = $this->createMock(VariantListingConfig::class);
        $variantConfig->method('getDisplayParent')->willReturn(true);
        $variantConfig->method('getConfiguratorGroupConfig')->willReturn([]);

        $product->method('getVariantListingConfig')->willReturn($variantConfig);
        $product->method('getActive')->willReturn(true);
        $product->method('getId')->willReturn('main-product-id');
        $product->method('getProductNumber')->willReturn('MAIN-PROD');

        $this->configProvider->method('isEnabledSyncFirstAvailableVariant')->willReturn(false);
        $this->systemConfigService->method('getBool')->willReturn(false);
        $this->configProvider->method('getProductIdentifier')->willReturn(ProductIdentifierOptions::PRODUCT_ID);

        $result = $this->productTaggingHelper->findProductId(
            $this->salesChannelContext,
            $product,
            null,
        );

        $this->assertEquals('main-product-id', $result);
    }

    public function testFindProductIdWithDisplayCheapestVariant(): void
    {
        $product = $this->createProductEntity();
        $product->method('getChildCount')->willReturn(2);
        $product->method('getId')->willReturn('main-product-id');
        $product->method('getProductNumber')->willReturn('MAIN-PRODUCT');
        $product->method('setChildren');

        $variantConfig = $this->createMock(VariantListingConfig::class);
        $variantConfig->method('getDisplayParent')->willReturn(false);
        $variantConfig->method('getDisplayCheapestVariant')->willReturn(true);
        $variantConfig->method('getConfiguratorGroupConfig')->willReturn([]);

        $product->method('getVariantListingConfig')->willReturn($variantConfig);

        // Create child variants with different prices
        $variant1 = $this->createProductEntity();
        $variant1->method('getId')->willReturn('variant-1-id');
        $variant1->method('getActive')->willReturn(true);
        $variant1->method('getCurrencyPrice')
            ->with('test-currency-id')
            ->willReturn($this->createPrice(15.0));
        $variant1->method('getProductNumber')->willReturn('VARIANT-1');
        $variant1->method('getIsCloseout')->willReturn(false);

        $variant2 = $this->createProductEntity();
        $variant2->method('getId')->willReturn('variant-2-id');
        $variant2->method('getActive')->willReturn(true);
        $variant2->method('getCurrencyPrice')
            ->with('test-currency-id')
            ->willReturn($this->createPrice(10.0)); // cheaper
        $variant2->method('getProductNumber')->willReturn('VARIANT-2');
        $variant2->method('getIsCloseout')->willReturn(false);
        $variant2->method('setChildren');

        $children = new ProductCollection([$variant1, $variant2]);
        $product->method('getChildren')->willReturn($children);

        $this->systemConfigService->method('getBool')->willReturn(false);
        $this->configProvider->method('getProductIdentifier')
            ->with('test-sales-channel-id', 'test-language-id')
            ->willReturn(ProductIdentifierOptions::PRODUCT_ID);

        $result = $this->productTaggingHelper->findProductId(
            $this->salesChannelContext,
            $product,
            null,
        );

        $this->assertEquals('variant-2-id', $result);
    }

    public function testFindProductIdWithMainVariant(): void
    {
        $product = $this->createProductEntity();
        $product->method('getChildCount')->willReturn(2);
        $product->method('getId')->willReturn('main-product-id');
        $product->method('getProductNumber')->willReturn('MAIN-PRODUCT');
        $product->method('setChildren');

        $mainVariantId = 'main-variant-id';
        $variantConfig = $this->createMock(VariantListingConfig::class);
        $variantConfig->method('getDisplayParent')->willReturn(false);
        $variantConfig->method('getDisplayCheapestVariant')->willReturn(false);
        $variantConfig->method('getMainVariantId')->willReturn($mainVariantId);
        $variantConfig->method('getConfiguratorGroupConfig')->willReturn([]);

        $product->method('getVariantListingConfig')->willReturn($variantConfig);

        $mainVariant = $this->createProductEntity();
        $mainVariant->method('getId')->willReturn($mainVariantId);
        $mainVariant->method('getActive')->willReturn(true);
        $mainVariant->method('getProductNumber')->willReturn('MAIN-VARIANT');
        $mainVariant->method('getIsCloseout')->willReturn(false);
        $mainVariant->method('setChildren');

        $otherVariant = $this->createProductEntity();
        $otherVariant->method('getId')->willReturn('other-variant-id');
        $otherVariant->method('getProductNumber')->willReturn('OTHER-VARIANT');
        $otherVariant->method('getActive')->willReturn(true);
        $otherVariant->method('getIsCloseout')->willReturn(false);

        $children = new ProductCollection([$mainVariant, $otherVariant]);
        $product->method('getChildren')->willReturn($children);

        $this->systemConfigService->method('getBool')->willReturn(false);
        $this->configProvider->method('isEnabledSyncFirstAvailableVariant')->willReturn(false);
        $this->productHelper->method('getProductStock')
            ->with($mainVariant, $this->salesChannelContext)
            ->willReturn(10);
        $this->configProvider->method('getProductIdentifier')
            ->with('test-sales-channel-id', 'test-language-id')
            ->willReturn(ProductIdentifierOptions::PRODUCT_ID);

        $result = $this->productTaggingHelper->findProductId(
            $this->salesChannelContext,
            $product,
            null,
        );

        $this->assertEquals($mainVariantId, $result);
    }

    public function testFindProductIdWithConfiguratorGroups(): void
    {
        $product = $this->createProductEntity();
        $product->method('getChildCount')->willReturn(2);
        $product->method('getId')->willReturn('main-product-id');
        $product->method('getProductNumber')->willReturn('MAIN-PRODUCT');
        $product->method('setChildren');

        $variantConfig = $this->createMock(VariantListingConfig::class);
        $variantConfig->method('getDisplayParent')->willReturn(false);
        $variantConfig->method('getDisplayCheapestVariant')->willReturn(false);
        $variantConfig->method('getMainVariantId')->willReturn(null);
        $variantConfig->method('getConfiguratorGroupConfig')->willReturn([
            [
                'expressionForListings' => true,
            ],
        ]);

        $product->method('getVariantListingConfig')->willReturn($variantConfig);

        $variant1 = $this->createProductEntity();
        $variant1->method('getId')->willReturn('variant-1-id');
        $variant1->method('getActive')->willReturn(true);
        $variant1->method('getDisplayGroup')->willReturn('group-1');
        $variant1->method('getProductNumber')->willReturn('VARIANT-1');
        $variant1->method('getIsCloseout')->willReturn(false);
        $variant1->method('setChildren');

        $variant2 = $this->createProductEntity();
        $variant2->method('getId')->willReturn('variant-2-id');
        $variant2->method('getActive')->willReturn(false);
        $variant2->method('getDisplayGroup')->willReturn('group-1');
        $variant2->method('getProductNumber')->willReturn('VARIANT-2');
        $variant2->method('getIsCloseout')->willReturn(false);
        $variant2->method('setChildren');

        $children = new ProductCollection([$variant1, $variant2]);
        $product->method('getChildren')->willReturn($children);

        $this->systemConfigService->method('getBool')->willReturn(false);
        $this->configProvider->method('isEnabledSyncFirstAvailableVariant')->willReturn(false);
        $this->configProvider->method('getProductIdentifier')
            ->with('test-sales-channel-id', 'test-language-id')
            ->willReturn(ProductIdentifierOptions::PRODUCT_ID);

        $result = $this->productTaggingHelper->findProductId(
            $this->salesChannelContext,
            $product,
            null,
        );

        $this->assertEquals('variant-1-id', $result);
    }

    public function testFindProductIdWithFirstActiveVariantFallback(): void
    {
        $product = $this->createProductEntity();
        $product->method('getChildCount')->willReturn(2);
        $product->method('getId')->willReturn('main-product-id');
        $product->method('getProductNumber')->willReturn('MAIN-PRODUCT');
        $product->method('setChildren');

        $variantConfig = $this->createMock(VariantListingConfig::class);
        $variantConfig->method('getDisplayParent')->willReturn(false);
        $variantConfig->method('getDisplayCheapestVariant')->willReturn(false);
        $variantConfig->method('getMainVariantId')->willReturn(null);
        $variantConfig->method('getConfiguratorGroupConfig')->willReturn([]);

        $product->method('getVariantListingConfig')->willReturn($variantConfig);

        $variant1 = $this->createProductEntity();
        $variant1->method('getId')->willReturn('variant-1-id');
        $variant1->method('getActive')->willReturn(false);
        $variant1->method('getProductNumber')->willReturn('VARIANT-1');
        $variant1->method('getIsCloseout')->willReturn(false);
        $variant1->method('setChildren');

        $variant2 = $this->createProductEntity();
        $variant2->method('getId')->willReturn('variant-2-id');
        $variant2->method('getActive')->willReturn(true);
        $variant2->method('getProductNumber')->willReturn('VARIANT-2');
        $variant2->method('getIsCloseout')->willReturn(false);
        $variant2->method('setChildren');

        $children = new ProductCollection([$variant1, $variant2]);
        $product->method('getChildren')->willReturn($children);

        $this->systemConfigService->method('getBool')->willReturn(false);
        $this->configProvider->method('isEnabledSyncFirstAvailableVariant')->willReturn(false);
        $this->configProvider->method('getProductIdentifier')
            ->with('test-sales-channel-id', 'test-language-id')
            ->willReturn(ProductIdentifierOptions::PRODUCT_ID);

        $result = $this->productTaggingHelper->findProductId(
            $this->salesChannelContext,
            $product,
            null,
        );

        $this->assertEquals('variant-2-id', $result);
    }

    public function testFindProductIdWithHideProductsAfterClearanceEnabled(): void
    {
        $product = $this->createProductEntity();
        $product->method('getChildCount')->willReturn(1);
        $product->method('getActive')->willReturn(true);
        $product->method('getIsCloseout')->willReturn(true);
        $product->method('getId')->willReturn('main-product-id');
        $product->method('getProductNumber')->willReturn('MAIN-PRODUCT');
        $product->method('setChildren');

        $variantConfig = $this->createMock(VariantListingConfig::class);
        $variantConfig->method('getDisplayParent')->willReturn(true);
        $variantConfig->method('getConfiguratorGroupConfig')->willReturn([]);

        $product->method('getVariantListingConfig')->willReturn($variantConfig);

        $variant = $this->createProductEntity();
        $variant->method('getId')->willReturn('available-variant-id');
        $variant->method('getActive')->willReturn(true);
        $variant->method('getIsCloseout')->willReturn(false);
        $variant->method('getProductNumber')->willReturn('AVAILABLE-VARIANT');
        $variant->method('setChildren');

        $children = new ProductCollection([$variant]);
        $product->method('getChildren')->willReturn($children);

        $this->systemConfigService->method('getBool')
            ->with('core.listing.hideCloseoutProductsWhenOutOfStock', 'test-sales-channel-id')
            ->willReturn(true);

        $this->configProvider->method('isEnabledSyncFirstAvailableVariant')->willReturn(true);
        $this->productHelper->method('getProductStock')
            ->willReturnMap([
                [$product, $this->salesChannelContext, 0], // Main product out of stock
                [$variant, $this->salesChannelContext, 5],  // Variant in stock
            ]);
        $this->configProvider->method('getProductIdentifier')
            ->with('test-sales-channel-id', 'test-language-id')
            ->willReturn(ProductIdentifierOptions::PRODUCT_ID);

        $result = $this->productTaggingHelper->findProductId(
            $this->salesChannelContext,
            $product,
            null,
        );

        $this->assertEquals('available-variant-id', $result);
    }

    private function createProductEntity(): MockObject|ProductEntity
    {
        $product = $this->createMock(ProductEntity::class);
        $product->method('getVariantListingConfig')->willReturn(null);
        $product->method('getChildren')->willReturn(new ProductCollection());

        return $product;
    }

    private function createPrice(float $netPrice): Price
    {
        return new Price(
            'test-currency-id',
            $netPrice,
            $netPrice * 1.19, // gross with 19% tax
            false,
        );
    }
}
