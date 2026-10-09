<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Twig\Extension;

use Nosto\Model\Product\Product as NostoProduct;
use Nosto\NostoIntegration\Model\Config\NostoConfigService;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Category\Builder as CategoryBuilder;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\ProductProviderInterface;
use Nosto\NostoIntegration\Twig\Extension\NostoExtension;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class NostoExtensionTest extends TestCase
{
    public function testGetNostoProductLoadsChildrenWhenParentStockCalculationIsEnabled(): void
    {
        $this->assertChildrenLoading(true);
    }

    public function testGetNostoProductDoesNotLoadChildrenWhenParentStockCalculationIsDisabled(): void
    {
        $this->assertChildrenLoading(false);
    }

    private function assertChildrenLoading(bool $enabled): void
    {
        $salesChannelId = 'sales-channel-id';
        $languageId = 'language-id';
        $productId = 'product-id';

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn($salesChannelId);
        $context->method('getLanguageId')->willReturn($languageId);

        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->expects(self::once())
            ->method('isEnabledCalculateParentStockFromVariants')
            ->with($salesChannelId, $languageId)
            ->willReturn($enabled);

        $productHelper = $this->createMock(ProductHelper::class);
        $productHelper->expects(self::once())
            ->method('getShopwareProductsPartial')
            ->with([$productId], $context, $enabled)
            ->willReturn(new EntityCollection());

        $nostoProduct = new class() extends NostoProduct {
            public string $variationStatus;
        };
        $productProvider = $this->createMock(ProductProviderInterface::class);
        $productProvider->expects(self::once())
            ->method('get')
            ->willReturn($nostoProduct);

        $extension = new NostoExtension(
            $productProvider,
            $this->createMock(PartialProvider::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(SalesChannelRepository::class),
            $this->createMock(SystemConfigService::class),
            $configProvider,
            $this->createMock(NostoConfigService::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(CategoryBuilder::class),
            $productHelper,
        );

        $product = new SalesChannelProductEntity();
        $product->setId($productId);

        self::assertSame($nostoProduct, $extension->getNostoProduct($product, $context));
    }
}
