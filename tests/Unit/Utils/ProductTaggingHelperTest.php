<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Utils;

use Nosto\NostoIntegration\Decorator\Core\Content\Product\DataAbstractionLayer\VariantListingConfig;
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
    ): ProductEntity {
        $product = new ProductEntity();
        $product->setId($id);
        $product->setActive($active);
        $product->setStock($stock);
        $product->setIsCloseout($closeout);
        $product->setChildCount($children?->count() ?? 0);
        $product->setChildren($children ?? new ProductCollection());
        $product->setVariantListingConfig($variantListingConfig);

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
