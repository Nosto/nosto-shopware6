<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Model\Nosto\Entity\Product;

use Nosto\NostoIntegration\Enums\ProductIdentifierOptions;
use Nosto\NostoIntegration\Enums\StockFieldOptions;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\CrossSelling\CrossSellingBuilder;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\SkuBuilder;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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

    private function createContext(): SalesChannelContext
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sales-channel-id');
        $context->method('getLanguageId')->willReturn('language-id');
        $context->method('getCurrencyId')->willReturn('eur-id');

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
    ): ProductEntity {
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getId',
                'getProductNumber',
                'getTranslation',
                'getStock',
                'getAvailableStock',
                'getCurrencyPrice',
                'getCover',
                'getManufacturer',
                'getCustomFields',
                'getShippingFree',
                'getOptions',
                'getProperties',
                'getVisibilities',
                'getCustomSearchKeywords',
                'getReleaseDate',
                'getManufacturerNumber',
                'getEan',
            ])
            ->getMock();

        $product->method('getId')->willReturn($id);
        $product->method('getProductNumber')->willReturn($productNumber);
        $product->method('getTranslation')->willReturnCallback(
            static fn (string $field) => $field === 'name' ? $name : null,
        );
        $product->method('getStock')->willReturn($stock);
        $product->method('getAvailableStock')->willReturn($availableStock);
        $product->method('getCurrencyPrice')->willReturnCallback(
            static function (string $currencyId) use ($priceCollection): ?Price {
                if ($priceCollection === null) {
                    return null;
                }

                return $priceCollection->get($currencyId);
            },
        );
        $product->method('getCover')->willReturnCallback(
            static function () use ($coverUrl): ?ProductMediaEntity {
                if ($coverUrl === null) {
                    return null;
                }

                $media = new MediaEntity();
                $media->setUrl($coverUrl);
                $cover = new ProductMediaEntity();
                $cover->setMedia($media);

                return $cover;
            },
        );
        $product->method('getManufacturer')->willReturnCallback(
            static function () use ($manufacturerUrl): ?ProductManufacturerEntity {
                if ($manufacturerUrl === null) {
                    return null;
                }

                $media = new MediaEntity();
                $media->setUrl($manufacturerUrl);
                $manufacturer = new ProductManufacturerEntity();
                $manufacturer->setMedia($media);

                return $manufacturer;
            },
        );
        $product->method('getCustomFields')->willReturn($customFields);
        $product->method('getShippingFree')->willReturn(true);
        $product->method('getOptions')->willReturn(null);
        $product->method('getProperties')->willReturn(null);
        $product->method('getVisibilities')->willReturn(new ProductVisibilityCollection());
        $product->method('getCustomSearchKeywords')->willReturn(null);
        $product->method('getReleaseDate')->willReturn(null);
        $product->method('getManufacturerNumber')->willReturn(null);
        $product->method('getEan')->willReturn(null);

        return $product;
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
