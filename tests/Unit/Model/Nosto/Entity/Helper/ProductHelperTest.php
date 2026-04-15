<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Model\Nosto\Entity\Helper;

use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractProductDetailRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class ProductHelperTest extends TestCase
{
    public function testGetProductsIteratorAppliesInactiveAndCategoryBlocklistFilters(): void
    {
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isEnabledSyncInactiveProducts')->willReturn(false);
        $configProvider->method('getCategoryBlocklist')->willReturn(['category-blocked']);

        $productRepository = $this->createMock(EntityRepository::class);
        $productRepository->method('getDefinition')->willReturn($this->createDefinition('product_test'));

        $capturedCriteria = null;
        $productRepository->method('search')->willReturnCallback(
            static function (Criteria $criteria, Context $context) use (&$capturedCriteria): EntitySearchResult {
                $capturedCriteria = $criteria;
                $product = new ProductEntity();
                $product->setId(Uuid::randomHex());
                $product->setProductNumber('SWDEMO10001');

                return new EntitySearchResult(
                    ProductEntity::class,
                    1,
                    new EntityCollection([$product]),
                    new AggregationResultCollection(),
                    $criteria,
                    $context,
                );
            },
        );

        $helper = $this->createHelper(
            productRepository: $productRepository,
            configProvider: $configProvider,
        );

        $context = $this->createContext();
        $iterator = $helper->getProductsIterator(['product-id-1', 'product-id-2'], $context);
        $iterator->fetch();

        self::assertInstanceOf(Criteria::class, $capturedCriteria);
        self::assertTrue($capturedCriteria->hasEqualsFilter('active'));
        self::assertContainsOnlyInstancesOf(NotFilter::class, array_filter(
            $capturedCriteria->getFilters(),
            static fn ($filter): bool => $filter instanceof NotFilter,
        ));
        self::assertContainsOnlyInstancesOf(EqualsAnyFilter::class, array_filter(
            $capturedCriteria->getFilters(),
            static fn ($filter): bool => $filter instanceof EqualsAnyFilter,
        ));
        self::assertNotEmpty($capturedCriteria->getFilters());
    }

    public function testGetProductsIteratorDoesNotApplyInactiveFilterWhenInactiveSyncIsEnabled(): void
    {
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isEnabledSyncInactiveProducts')->willReturn(true);
        $configProvider->method('getCategoryBlocklist')->willReturn([]);

        $productRepository = $this->createMock(EntityRepository::class);
        $productRepository->method('getDefinition')->willReturn($this->createDefinition('product_test'));

        $capturedCriteria = null;
        $productRepository->method('search')->willReturnCallback(
            static function (Criteria $criteria, Context $context) use (&$capturedCriteria): EntitySearchResult {
                $capturedCriteria = $criteria;

                return new EntitySearchResult(
                    ProductEntity::class,
                    0,
                    new EntityCollection(),
                    new AggregationResultCollection(),
                    $criteria,
                    $context,
                );
            },
        );

        $helper = $this->createHelper(
            productRepository: $productRepository,
            configProvider: $configProvider,
        );

        $helper->getProductsIterator(['product-id-1'], $this->createContext())->fetch();

        self::assertInstanceOf(Criteria::class, $capturedCriteria);
        self::assertFalse($capturedCriteria->hasEqualsFilter('active'));
    }

    public function testGetShopwareProductsPartialAddsChildrenFiltersWhenInactiveSyncIsDisabled(): void
    {
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isEnabledSyncInactiveProducts')->willReturn(false);
        $configProvider->method('getCategoryBlocklist')->willReturn(['category-blocked']);

        $productRepository = $this->createMock(EntityRepository::class);
        $productRepository->method('getDefinition')->willReturn($this->createDefinition('product_test'));

        $capturedCriteria = null;
        $salesChannelRepository = $this->createMock(SalesChannelRepository::class);
        $salesChannelRepository->method('search')->willReturnCallback(
            static function (Criteria $criteria, mixed $context) use (&$capturedCriteria): EntitySearchResult {
                $capturedCriteria = $criteria;

                return new EntitySearchResult(
                    ProductEntity::class,
                    0,
                    new EntityCollection(),
                    new AggregationResultCollection(),
                    $criteria,
                    Context::createDefaultContext(),
                );
            },
        );

        $helper = $this->createHelper(
            productRepository: $productRepository,
            configProvider: $configProvider,
            salesChannelRepository: $salesChannelRepository,
        );

        $context = $this->createContext();
        $helper->getShopwareProductsPartial(['product-id-1'], $context);

        self::assertInstanceOf(Criteria::class, $capturedCriteria);
        $childrenCriteria = $capturedCriteria->getAssociation('children');
        self::assertTrue($childrenCriteria->hasEqualsFilter('active'));
        self::assertNotEmpty(array_filter(
            $childrenCriteria->getFilters(),
            static fn ($filter): bool => $filter instanceof NotFilter,
        ));
    }

    public function testGetShopwareProductsPartialDoesNotAddChildrenInactiveFilterWhenInactiveSyncIsEnabled(): void
    {
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isEnabledSyncInactiveProducts')->willReturn(true);
        $configProvider->method('getCategoryBlocklist')->willReturn([]);

        $productRepository = $this->createMock(EntityRepository::class);
        $productRepository->method('getDefinition')->willReturn($this->createDefinition('product_test'));

        $capturedCriteria = null;
        $salesChannelRepository = $this->createMock(SalesChannelRepository::class);
        $salesChannelRepository->method('search')->willReturnCallback(
            static function (Criteria $criteria, mixed $context) use (&$capturedCriteria): EntitySearchResult {
                $capturedCriteria = $criteria;

                return new EntitySearchResult(
                    ProductEntity::class,
                    0,
                    new EntityCollection(),
                    new AggregationResultCollection(),
                    $criteria,
                    Context::createDefaultContext(),
                );
            },
        );

        $helper = $this->createHelper(
            productRepository: $productRepository,
            configProvider: $configProvider,
            salesChannelRepository: $salesChannelRepository,
        );

        $helper->getShopwareProductsPartial(['product-id-1'], $this->createContext());

        self::assertInstanceOf(Criteria::class, $capturedCriteria);
        $childrenCriteria = $capturedCriteria->getAssociation('children');
        self::assertFalse($childrenCriteria->hasEqualsFilter('active'));
    }

    private function createHelper(
        ?EntityRepository $productRepository = null,
        ?ConfigProvider $configProvider = null,
        ?SalesChannelRepository $salesChannelRepository = null,
    ): ProductHelper {
        $productRepository ??= $this->createMock(EntityRepository::class);
        $configProvider ??= $this->createMock(ConfigProvider::class);
        $productRoute = $this->createMock(AbstractProductDetailRoute::class);
        $reviewRepository = $this->createMock(EntityRepository::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $seoUrlReplacer = $this->createMock(\Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface::class);
        $salesChannelRepository ??= $this->createMock(SalesChannelRepository::class);
        $router = $this->createMock(RouterInterface::class);
        $router->method('getContext')->willReturn(new \Symfony\Component\Routing\RequestContext());

        return new ProductHelper(
            $productRepository,
            $productRoute,
            $reviewRepository,
            $eventDispatcher,
            $configProvider,
            $seoUrlReplacer,
            $salesChannelRepository,
            $router,
            $this->createMock(LoggerInterface::class),
        );
    }

    private function createContext(): SalesChannelContext
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sales-channel-id');
        $context->method('getLanguageId')->willReturn('language-id');
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        return $context;
    }

    private function createDefinition(string $entityName): EntityDefinition
    {
        $definition = new class() extends EntityDefinition {
            public string $entityName;

            public function getEntityName(): string
            {
                return $this->entityName;
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection();
            }
        };
        $definition->entityName = $entityName;

        $definition->compile($this->createMock(DefinitionInstanceRegistry::class));

        return $definition;
    }
}
