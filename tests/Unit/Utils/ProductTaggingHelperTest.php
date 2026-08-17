<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Utils;

use Nosto\NostoIntegration\Decorator\Core\Content\Product\DataAbstractionLayer\VariantListingConfig;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProduct;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProductCollection;
use Nosto\NostoIntegration\Utils\ProductTaggingHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class ProductTaggingHelperTest extends TestCase
{
    public function testFindProductIdKeepsActiveDisplayParentProductWhenItIsInStock(): void
    {
        $helper = $this->createHelper(false, false);
        $context = $this->createContext();
        $parent = $this->createProduct(
            'parent-id',
            true,
            false,
            12,
            12,
            new VariantListingConfig(true, null, null, false, false),
            [
                $this->createProduct('child-1', true, false, 4, 4, null),
                $this->createProduct('child-2', true, false, 2, 2, null),
            ],
        );

        $result = $helper->findProductId($context, $parent, null, false, true);

        self::assertInstanceOf(PartialProductCollection::class, $result);
        self::assertSame(['parent-id'], $result->getIds());
    }

    public function testFindProductIdFallsBackToFirstActiveChildWhenParentIsInactive(): void
    {
        $helper = $this->createHelper(false, false);
        $context = $this->createContext();
        $parent = $this->createProduct(
            'parent-id',
            false,
            false,
            0,
            0,
            new VariantListingConfig(true, null, null, false, false),
            [
                $this->createProduct('child-active', true, false, 7, 7, null),
                $this->createProduct('child-inactive', false, false, 9, 9, null),
            ],
        );

        $result = $helper->findProductId($context, $parent, null, false, true);

        self::assertInstanceOf(PartialProductCollection::class, $result);
        self::assertSame(['child-active'], $result->getIds());
    }

    public function testFindProductIdUsesFirstAvailableChildForClearanceParentWhenConfigured(): void
    {
        $context = $this->createContext();
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->expects($this->once())
            ->method('isEnabledSyncFirstAvailableVariant')
            ->with($context->getSalesChannelId(), $context->getLanguageId())
            ->willReturn(true);

        $helper = $this->createHelper(true, true, $configProvider);
        $parent = $this->createProduct(
            'parent-id',
            true,
            true,
            0,
            0,
            new VariantListingConfig(true, null, null, false, false),
            [
                $this->createProduct('child-out-of-stock', true, true, 0, 0, null),
                $this->createProduct('child-available', true, true, 6, 6, null),
            ],
        );

        $result = $helper->findProductId($context, $parent, null, false, true);

        self::assertInstanceOf(PartialProductCollection::class, $result);
        self::assertSame(['child-available'], $result->getIds());
    }

    public function testFindProductIdSelectsCheapestInStockChildWhenConfigured(): void
    {
        $helper = $this->createHelper(false, false);
        $context = $this->createContext();
        $parent = $this->createProduct(
            'parent-id',
            true,
            false,
            10,
            10,
            new VariantListingConfig(false, null, null, true, false),
            [
                $this->createProduct(
                    'child-cheap-out-of-stock',
                    true,
                    false,
                    0,
                    0,
                    null,
                    [],
                    $this->createPriceCollection('currency-id', 10.0, 10.0),
                ),
                $this->createProduct(
                    'child-expensive-in-stock',
                    true,
                    false,
                    4,
                    4,
                    null,
                    [],
                    $this->createPriceCollection('currency-id', 20.0, 20.0),
                ),
            ],
        );

        $result = $helper->findProductId($context, $parent, null, false, true);

        self::assertInstanceOf(PartialProductCollection::class, $result);
        self::assertSame(['child-expensive-in-stock'], $result->getIds());
    }

    public function testFindProductIdUsesConfiguredMainVariantWhenProductSyncIsEnabled(): void
    {
        $helper = $this->createHelper(false, false);
        $context = $this->createContext();
        $parent = $this->createProduct(
            'parent-id',
            true,
            false,
            8,
            8,
            new VariantListingConfig(false, 'child-main', null, false, false),
            [
                $this->createProduct('child-main', true, false, 4, 4, null),
                $this->createProduct('child-secondary', true, false, 2, 2, null),
            ],
        );

        $result = $helper->findProductId($context, $parent, null, false, true);

        self::assertInstanceOf(PartialProductCollection::class, $result);
        self::assertSame(['child-main'], $result->getIds());
    }

    public function testFindProductIdSelectsSpecificConfiguratorGroupVariantWhenProductSyncIsEnabled(): void
    {
        $helper = $this->createHelper(false, false);
        $context = $this->createContext();
        $parent = $this->createProduct(
            'parent-id',
            true,
            false,
            8,
            8,
            new VariantListingConfig(
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
            [
                $this->createProduct('color-red', true, false, 4, 4, null, [], null, 'color'),
                $this->createProduct('color-blue', true, false, 2, 2, null, [], null, 'color'),
            ],
        );
        $selectedVariant = $this->createProduct(
            'color-red',
            true,
            false,
            4,
            4,
            null,
            [],
            null,
            'color',
        );

        $result = $helper->findProductId($context, $parent, $selectedVariant, false, true);

        self::assertInstanceOf(PartialProductCollection::class, $result);
        self::assertSame(['color-red'], $result->getIds());
    }

    public function testFindProductIdReturnsOneVariantPerConfiguratorGroupWhenNoVariantIsPreselected(): void
    {
        $helper = $this->createHelper(false, false);
        $context = $this->createContext();
        $parent = $this->createProduct(
            'parent-id',
            true,
            false,
            8,
            8,
            new VariantListingConfig(
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
            [
                $this->createProduct('group-a-first', true, false, 4, 4, null, [], null, 'group-a'),
                $this->createProduct('group-a-second', true, false, 2, 2, null, [], null, 'group-a'),
                $this->createProduct('group-b-first', true, false, 3, 3, null, [], null, 'group-b'),
            ],
        );

        $result = $helper->findProductId($context, $parent, null, false, true);

        self::assertInstanceOf(PartialProductCollection::class, $result);
        self::assertSame(['group-a-first', 'group-b-first'], $result->getIds());
    }

    private function createHelper(
        bool $hideCloseoutProductsWhenOutOfStock,
        bool $syncFirstAvailableVariant,
        ?ConfigProvider $configProvider = null,
    ): ProductTaggingHelper {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('getBool')->willReturn($hideCloseoutProductsWhenOutOfStock);

        if ($configProvider === null) {
            $configProvider = $this->createMock(ConfigProvider::class);
            $configProvider->method('isEnabledSyncFirstAvailableVariant')->willReturn($syncFirstAvailableVariant);
        }

        $productHelper = $this->createMock(ProductHelper::class);
        $productHelper->method('getProductStock')->willReturnCallback(
            static fn (PartialProduct $product, SalesChannelContext $context): int => $product->getStock(),
        );

        return new ProductTaggingHelper(
            $systemConfigService,
            $configProvider,
            null,
            $productHelper,
            null,
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

    /**
     * @param PartialProduct[] $children
     */
    private function createProduct(
        string $id,
        bool $active,
        bool $isCloseout,
        int $stock,
        int $availableStock,
        ?VariantListingConfig $variantListingConfig,
        array $children = [],
        ?PriceCollection $priceCollection = null,
        ?string $displayGroup = null,
    ): PartialProduct {
        $entity = new PartialEntity([
            'id' => $id,
            'productNumber' => strtoupper($id),
            'active' => $active,
            'isCloseout' => $isCloseout,
            'stock' => $stock,
            'availableStock' => $availableStock,
            'variantListingConfig' => $variantListingConfig,
            'price' => $priceCollection,
            'displayGroup' => $displayGroup,
        ]);

        if ($children !== []) {
            $entity->set('children', new PartialProductCollection($children));
        }

        return new PartialProduct($entity);
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
