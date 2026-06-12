<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Model\Nosto\Entity\Product;

use Nosto\NostoIntegration\Enums\ProductIdentifierOptions;
use Nosto\NostoIntegration\Enums\StockFieldOptions;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\CrossSelling\CrossSellingBuilder;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProduct;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProductConverter;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\SkuBuilder;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class SkuBuilderTest extends TestCase
{
    public function testBuildSetsMandatoryFieldsAndFallbackImageWhenCoverIsMissing(): void
    {
        $context = $this->createContext();
        $product = $this->createProduct(
            id: 'product-id',
            productNumber: 'SWDEMO10002',
            name: 'Example product',
            stock: 0,
            availableStock: 0,
            priceCollection: $this->createPriceCollection('eur-id', 10.0, 12.0, 9.0, 11.0),
        );

        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('getProductIdentifier')->willReturn(ProductIdentifierOptions::PRODUCT_NUMBER);
        $configProvider->method('getStockField')->willReturn(StockFieldOptions::ACTUAL_STOCK);
        $configProvider->method('isEnabledProductProperties')->willReturn(false);
        $configProvider->method('isEnabledInventoryLevels')->willReturn(true);
        $configProvider->method('isEnabledProductLabellingSync')->willReturn(false);
        $configProvider->method('isEnabledAlternateImages')->willReturn(false);

        $productHelper = $this->createMock(ProductHelper::class);
        $productHelper->expects($this->once())
            ->method('getProductUrl')
            ->with($product, $context)
            ->willReturn('https://shop.example.test/product/example');
        $productHelper->expects($this->once())
            ->method('getFallbackImageUrl')
            ->with($context)
            ->willReturn('https://cdn.example.test/placeholder.svg');

        $crossSellingBuilder = $this->createMock(CrossSellingBuilder::class);
        $crossSellingBuilder->method('build')->willReturn([]);

        $builder = new SkuBuilder(
            $configProvider,
            $productHelper,
            $this->createMock(NetPriceCalculator::class),
            new CashRounding(),
            $crossSellingBuilder,
            $this->createMock(\Shopware\Core\Framework\DataAbstractionLayer\EntityRepository::class),
        );

        $sku = $builder->build($product, $context);

        self::assertSame('SWDEMO10002', $sku->getId());
        self::assertSame('Example product', $sku->getName());
        self::assertSame('https://shop.example.test/product/example', $sku->getUrl());
        self::assertSame('https://cdn.example.test/placeholder.svg', $sku->getImageUrl());
        self::assertSame('OutOfStock', $sku->getAvailability());
        self::assertSame(12.0, $sku->getPrice());
        self::assertSame(11.0, $sku->getListPrice());
        self::assertSame(0, $sku->getInventoryLevel());
        self::assertSame('SWDEMO10002', $sku->getCustomFields()['productnumber']);
        self::assertSame('product-id', $sku->getCustomFields()['productid']);
    }

    public function testBuildExportsSelectedCustomFieldsAndBrandImageWhenEnabled(): void
    {
        $context = $this->createContext();
        $product = $this->createProduct(
            id: 'product-id',
            productNumber: 'SWDEMO10003',
            name: 'Configured product',
            stock: 4,
            availableStock: 4,
            priceCollection: $this->createPriceCollection('eur-id', 20.0, 23.8),
            coverUrl: 'https://media.example.test/cover.jpg',
            manufacturerUrl: 'https://media.example.test/brand.jpg',
            customFields: [
                'foo' => 'bar',
                'meta' => [
                    'nested' => 'value',
                ],
                'ignored' => 'nope',
            ],
        );

        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('getProductIdentifier')->willReturn(ProductIdentifierOptions::PRODUCT_ID);
        $configProvider->method('getStockField')->willReturn(StockFieldOptions::AVAILABLE_STOCK);
        $configProvider->method('isEnabledProductProperties')->willReturn(true);
        $configProvider->method('getSelectedCustomFields')->willReturn(['foo', 'meta']);
        $configProvider->method('isEnabledInventoryLevels')->willReturn(false);
        $configProvider->method('isEnabledProductLabellingSync')->willReturn(false);
        $configProvider->method('isEnabledAlternateImages')->willReturn(false);

        $productHelper = $this->createMock(ProductHelper::class);
        $productHelper->expects($this->once())
            ->method('getProductUrl')
            ->with($product, $context)
            ->willReturn('https://shop.example.test/product/configured');
        $productHelper->expects($this->never())
            ->method('getFallbackImageUrl');

        $crossSellingBuilder = $this->createMock(CrossSellingBuilder::class);
        $crossSellingBuilder->method('build')->willReturn([]);

        $builder = new SkuBuilder(
            $configProvider,
            $productHelper,
            $this->createMock(NetPriceCalculator::class),
            new CashRounding(),
            $crossSellingBuilder,
            $this->createMock(\Shopware\Core\Framework\DataAbstractionLayer\EntityRepository::class),
        );

        $sku = $builder->build($product, $context);

        self::assertSame('product-id', $sku->getId());
        self::assertSame('Configured product', $sku->getName());
        self::assertSame('https://media.example.test/cover.jpg', $sku->getImageUrl());
        self::assertSame('InStock', $sku->getAvailability());

        $customFields = $sku->getCustomFields();
        self::assertArrayNotHasKey('foo', $customFields);
        self::assertArrayNotHasKey('meta', $customFields);
        self::assertArrayHasKey('brand-image-url', $customFields);
        self::assertSame('https://media.example.test/brand.jpg', $customFields['brand-image-url']);
        self::assertSame('true', $customFields['Shipping Free'] ?? null);
    }

    public function testBuildPrefersCalculatedPriceWhenMultiCurrencyIsDisabled(): void
    {
        $context = $this->createContext();
        $product = $this->createProduct(
            id: 'product-id',
            productNumber: 'SWDEMO10004',
            name: 'Calculated product',
            stock: 1,
            availableStock: 1,
            priceCollection: $this->createPriceCollection('eur-id', 10.0, 12.0, 9.0, 11.0),
        );

        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('getProductIdentifier')->willReturn(ProductIdentifierOptions::PRODUCT_NUMBER);
        $configProvider->method('getStockField')->willReturn(StockFieldOptions::ACTUAL_STOCK);
        $configProvider->method('isEnabledProductProperties')->willReturn(false);
        $configProvider->method('isEnabledInventoryLevels')->willReturn(false);
        $configProvider->method('isEnabledProductLabellingSync')->willReturn(false);
        $configProvider->method('isEnabledMultiCurrency')->willReturn(false);

        $calculatedPrice = new CalculatedPrice(
            25.0,
            25.0,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            1,
            null,
            ListPrice::createFromUnitPrice(25.0, 30.0),
        );

        $productHelper = $this->createMock(ProductHelper::class);
        $productHelper->method('getProductUrl')->willReturn('https://shop.example.test/product/calculated');
        $productHelper->method('getFallbackImageUrl')->willReturn('https://cdn.example.test/placeholder.svg');
        $productHelper->expects($this->once())
            ->method('getSalesChannelCalculatedPrice')
            ->with('product-id', $context)
            ->willReturn($calculatedPrice);

        $crossSellingBuilder = $this->createMock(CrossSellingBuilder::class);
        $crossSellingBuilder->method('build')->willReturn([]);

        $builder = new SkuBuilder(
            $configProvider,
            $productHelper,
            $this->createMock(NetPriceCalculator::class),
            new CashRounding(),
            $crossSellingBuilder,
            $this->createMock(\Shopware\Core\Framework\DataAbstractionLayer\EntityRepository::class),
        );

        $sku = $builder->build($product, $context);

        self::assertSame(25.0, $sku->getPrice());
        self::assertSame(30.0, $sku->getListPrice());
    }

    private function createContext(): SalesChannelContext
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sales-channel-id');
        $context->method('getLanguageId')->willReturn('language-id');
        $context->method('getCurrencyId')->willReturn('eur-id');
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        return $context;
    }

    private function createProduct(
        string $id,
        string $productNumber,
        string $name,
        int $stock,
        int $availableStock,
        ?PriceCollection $priceCollection = null,
        ?string $coverUrl = null,
        ?string $manufacturerUrl = null,
        array $customFields = [],
    ): PartialProduct {
        $manufacturer = null;
        if ($manufacturerUrl !== null) {
            $manufacturer = (object) [
                'media' => (object) [
                    'url' => $manufacturerUrl,
                ],
            ];
        }

        $cover = null;
        if ($coverUrl !== null) {
            $cover = (object) [
                'media' => (object) [
                    'url' => $coverUrl,
                ],
            ];
        }

        return PartialProductConverter::toPartialProduct(new PartialEntity([
            'id' => $id,
            'productNumber' => $productNumber,
            'translated' => [
                'name' => $name,
            ],
            'stock' => $stock,
            'availableStock' => $availableStock,
            'price' => $priceCollection,
            'cover' => $cover,
            'manufacturer' => $manufacturer,
            'customFields' => $customFields,
            'shippingFree' => true,
        ]));
    }

    private function createPriceCollection(
        string $currencyId,
        float $net,
        float $gross,
        ?float $listNet = null,
        ?float $listGross = null,
    ): PriceCollection {
        $price = new Price($currencyId, $net, $gross, true);
        if ($listNet !== null && $listGross !== null) {
            $price->setListPrice(new Price($currencyId, $listNet, $listGross, true));
        }

        $collection = new PriceCollection();
        $collection->add($price);

        return $collection;
    }
}
