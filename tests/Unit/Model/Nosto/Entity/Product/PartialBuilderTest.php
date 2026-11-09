<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Model\Nosto\Entity\Product;

use Nosto\Model\Product\Product as NostoProduct;
use Nosto\Model\Product\Sku as NostoSku;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\Category\TreeBuilder;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\CrossSelling\CrossSellingBuilder;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialBuilder;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProduct;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProductCollection;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProductConverter;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\SkuBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;

final class PartialBuilderTest extends TestCase
{
    public function testResetClearsAllInMemoryCaches(): void
    {
        $reflection = new ReflectionClass(PartialBuilder::class);
        $builder = $reflection->newInstanceWithoutConstructor();
        $cacheProperties = array_filter(
            $reflection->getProperties(ReflectionProperty::IS_PRIVATE),
            static function (ReflectionProperty $property): bool {
                $type = $property->getType();

                return str_ends_with($property->getName(), 'Cache')
                    && $type instanceof ReflectionNamedType
                    && $type->getName() === 'array';
            },
        );
        self::assertNotEmpty($cacheProperties);

        foreach ($cacheProperties as $property) {
            $property->setValue($builder, ['cached-value']);
        }

        self::assertInstanceOf(ResetInterface::class, $builder);

        $builder->reset();

        foreach ($cacheProperties as $property) {
            self::assertSame([], $property->getValue($builder));
        }
    }

    public function testSetPricesUsesSalesChannelCalculatedPriceWithMultiCurrencyEnabled(): void
    {
        $context = $this->createContext();
        $product = $this->createProduct('parent-id', 100.0, 120.0);
        $calculatedPrice = $this->createCalculatedPrice(50.0, 120.0);

        $productHelper = $this->createMock(ProductHelper::class);
        $productHelper->expects($this->once())
            ->method('getSalesChannelCalculatedPrice')
            ->with('parent-id', $context)
            ->willReturn($calculatedPrice);

        $builder = $this->createBuilder($productHelper);
        $nostoProduct = new NostoProduct();

        $builder->setPrices($nostoProduct, $product, $context);

        self::assertSame(50.0, $nostoProduct->getPrice());
        self::assertSame(120.0, $nostoProduct->getListPrice());
    }

    public function testSetPricesFallsBackToRawCurrencyPriceWhenCalculatedPriceIsUnavailable(): void
    {
        $context = $this->createContext(new CashRoundingConfig(2, 0.05, true));
        $product = $this->createProduct('parent-id', 100.01, 120.03);

        $productHelper = $this->createMock(ProductHelper::class);
        $productHelper->expects($this->once())
            ->method('getSalesChannelCalculatedPrice')
            ->with('parent-id', $context)
            ->willReturn(null);

        $builder = $this->createBuilder($productHelper);
        $nostoProduct = new NostoProduct();

        $builder->setPrices($nostoProduct, $product, $context);

        self::assertSame(100.0, $nostoProduct->getPrice());
        self::assertSame(120.05, $nostoProduct->getListPrice());
    }

    public function testChildPriceBatchAlsoWarmsTheParentCalculatedPriceCache(): void
    {
        $context = $this->createContext();
        $child = $this->createProduct('child-id', 100.0, 120.0);
        $parent = $this->createProduct('parent-id', 100.0, 120.0);
        $parent->setChildren(new PartialProductCollection([$child]));

        $productHelper = $this->createMock(ProductHelper::class);
        $productHelper->expects($this->once())
            ->method('getSalesChannelCalculatedPrices')
            ->with(['child-id', 'parent-id'], $context)
            ->willReturn([
                'child-id' => $this->createCalculatedPrice(50.0, 120.0),
                'parent-id' => $this->createCalculatedPrice(50.0, 120.0),
            ]);

        $skuBuilder = $this->createMock(SkuBuilder::class);
        $skuBuilder->expects($this->once())
            ->method('build')
            ->with($child, $context, self::isInstanceOf(CalculatedPrice::class))
            ->willReturn(new NostoSku());

        $builder = $this->createBuilder($productHelper, $skuBuilder);
        $method = new \ReflectionMethod($builder, 'preparingChildrenSkuCollection');

        $method->invoke($builder, $parent, $context);
    }

    private function createBuilder(ProductHelper $productHelper, ?SkuBuilder $skuBuilder = null): PartialBuilder
    {
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isEnabledProductSyncExtraLogging')->willReturn(false);

        return new PartialBuilder(
            $configProvider,
            $productHelper,
            $this->createMock(NetPriceCalculator::class),
            new CashRounding(),
            $skuBuilder ?? $this->createMock(SkuBuilder::class),
            $this->createMock(TreeBuilder::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(CrossSellingBuilder::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(SalesChannelRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class),
        );
    }

    private function createContext(?CashRoundingConfig $itemRounding = null): SalesChannelContext
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sales-channel-id');
        $context->method('getLanguageId')->willReturn('language-id');
        $context->method('getCurrencyId')->willReturn('eur-id');
        $context->method('getItemRounding')->willReturn($itemRounding ?? new CashRoundingConfig(2, 0.01, true));

        return $context;
    }

    private function createProduct(string $id, float $gross, float $listGross): PartialProduct
    {
        $price = new Price('eur-id', $gross, $gross, true);
        $price->setListPrice(new Price('eur-id', $listGross, $listGross, true));
        $prices = new PriceCollection([$price]);

        return PartialProductConverter::toPartialProduct(new PartialEntity([
            'id' => $id,
            'price' => $prices,
        ]));
    }

    private function createCalculatedPrice(float $unitPrice, float $listPrice): CalculatedPrice
    {
        return new CalculatedPrice(
            $unitPrice,
            $unitPrice,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            1,
            null,
            ListPrice::createFromUnitPrice($unitPrice, $listPrice),
        );
    }
}
