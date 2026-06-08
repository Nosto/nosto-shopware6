<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Model\Nosto\Entity\Helper;

use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
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
        self::assertTrue($this->criteriaHasEqualsFilter(
            $capturedCriteria,
            'visibilities.salesChannelId',
            'sales-channel-id',
        ));
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

    public function testGetProductsIteratorOmitsInactiveFilterWhenInactiveSyncIsEnabled(): void
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
        self::assertFalse($capturedCriteria->hasEqualsFilter('active'));
        self::assertEmpty(array_filter(
            $capturedCriteria->getFilters(),
            static fn ($filter): bool => $filter instanceof NotFilter,
        ));
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
        self::assertTrue($this->criteriaHasEqualsFilter(
            $capturedCriteria,
            'visibilities.salesChannelId',
            'sales-channel-id',
        ));
    }

    public function testGetShopwareProductsPartialAddsChildrenFiltersWhenInactiveSyncIsDisabled(): void
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
        );

        $context = $this->createContext();
        $helper->getShopwareProductsPartial(['product-id-1'], $context);

        self::assertInstanceOf(Criteria::class, $capturedCriteria);
        self::assertTrue($capturedCriteria->hasEqualsFilter('active'));
        self::assertNotEmpty(array_filter(
            $capturedCriteria->getFilters(),
            static fn ($filter): bool => $filter instanceof NotFilter,
        ));
        $childrenCriteria = $capturedCriteria->getAssociation('children');
        self::assertTrue($childrenCriteria->hasEqualsFilter('active'));
        self::assertNotEmpty(array_filter(
            $childrenCriteria->getFilters(),
            static fn ($filter): bool => $filter instanceof NotFilter,
        ));
        self::assertTrue($this->criteriaHasEqualsFilter(
            $capturedCriteria,
            'visibilities.salesChannelId',
            'sales-channel-id',
        ));
        self::assertTrue($this->criteriaHasEqualsFilter(
            $childrenCriteria,
            'visibilities.salesChannelId',
            'sales-channel-id',
        ));
    }

    public function testGetShopwareProductsPartialOmitsChildrenActiveFilterWhenInactiveSyncIsEnabled(): void
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
                    Context::createDefaultContext(),
                );
            },
        );

        $helper = $this->createHelper(
            productRepository: $productRepository,
            configProvider: $configProvider,
        );

        $context = $this->createContext();
        $helper->getShopwareProductsPartial(['product-id-1'], $context);

        self::assertInstanceOf(Criteria::class, $capturedCriteria);
        self::assertFalse($capturedCriteria->hasEqualsFilter('active'));
        $childrenCriteria = $capturedCriteria->getAssociation('children');
        self::assertFalse($childrenCriteria->hasEqualsFilter('active'));
        self::assertEmpty(array_filter(
            $childrenCriteria->getFilters(),
            static fn ($filter): bool => $filter instanceof NotFilter,
        ));
        self::assertTrue($this->criteriaHasEqualsFilter(
            $capturedCriteria,
            'visibilities.salesChannelId',
            'sales-channel-id',
        ));
        self::assertTrue($this->criteriaHasEqualsFilter(
            $childrenCriteria,
            'visibilities.salesChannelId',
            'sales-channel-id',
        ));
    }

    public function testGetShopwareProductsPartialUsesBaseProductRepositoryInsteadOfSalesChannelRepository(): void
    {
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isEnabledSyncInactiveProducts')->willReturn(true);
        $configProvider->method('getCategoryBlocklist')->willReturn([]);

        $productRepository = $this->createMock(EntityRepository::class);
        $productRepository->method('getDefinition')->willReturn($this->createDefinition('product_test'));
        $productRepository->expects(self::once())
            ->method('search')
            ->with(
                self::isInstanceOf(Criteria::class),
                self::isInstanceOf(Context::class),
            )
            ->willReturnCallback(
                static function (Criteria $criteria, Context $context): EntitySearchResult {
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

        $salesChannelRepository = $this->createMock(SalesChannelRepository::class);
        $salesChannelRepository->expects(self::never())->method('search');

        $helper = $this->createHelper(
            productRepository: $productRepository,
            configProvider: $configProvider,
            salesChannelRepository: $salesChannelRepository,
        );

        $helper->getShopwareProductsPartial(['product-id-1'], $this->createContext());
    }

    public function testGetShopwareProductsPartialCanRequireSalesChannelVisibility(): void
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

        $helper->getShopwareProductsPartial(['product-id-1'], $this->createContext());

        self::assertInstanceOf(Criteria::class, $capturedCriteria);
        self::assertTrue($this->criteriaHasEqualsFilter(
            $capturedCriteria,
            'visibilities.salesChannelId',
            'sales-channel-id',
        ));
    }

    public function testGetShopwareProductsPartialSkipsChildrenAssociationFiltersWhenIncludeChildrenIsFalse(): void
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

        $helper->getShopwareProductsPartial(['product-id-1'], $this->createContext(), false);

        self::assertInstanceOf(Criteria::class, $capturedCriteria);
        self::assertTrue($capturedCriteria->hasEqualsFilter('active'));
        self::assertNotEmpty(array_filter(
            $capturedCriteria->getFilters(),
            static fn ($filter): bool => $filter instanceof NotFilter,
        ));
        self::assertFalse($capturedCriteria->hasAssociation('children'));
        self::assertTrue($this->criteriaHasEqualsFilter(
            $capturedCriteria,
            'visibilities.salesChannelId',
            'sales-channel-id',
        ));
    }

    public function testGetReviewsCountCountsWholeFamilyWhenSyncingVariant(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        // Simulate the "Expand property values in product listings" case where the synced
        // product is a child variant rather than the parent.
        $variant = new SalesChannelProductEntity();
        $variant->setId($variantId);
        $variant->setParentId($parentId);

        $capturedCriteria = null;
        $reviewRepository = $this->createMock(EntityRepository::class);
        $reviewRepository->method('aggregate')->willReturnCallback(
            static function (Criteria $criteria, Context $context) use (&$capturedCriteria): AggregationResultCollection {
                $capturedCriteria = $criteria;

                return new AggregationResultCollection([new CountResult('review-count', 7)]);
            },
        );

        $helper = $this->createHelper(reviewRepository: $reviewRepository);

        $count = $helper->getReviewsCount($variant, $this->createContext());

        self::assertSame(7, $count);
        self::assertInstanceOf(Criteria::class, $capturedCriteria);
        // The review count must target the family root (parent id), sweeping in the
        // parent's reviews and all sibling variants - not just the synced variant.
        self::assertTrue($this->reviewCriteriaTargetsFamilyId($capturedCriteria, $parentId));
        self::assertFalse($this->reviewCriteriaTargetsFamilyId($capturedCriteria, $variantId));
    }

    public function testGetReviewsCountUsesOwnIdWhenSyncingParent(): void
    {
        $parentId = Uuid::randomHex();

        $parent = new SalesChannelProductEntity();
        $parent->setId($parentId);
        $parent->setParentId(null);

        $capturedCriteria = null;
        $reviewRepository = $this->createMock(EntityRepository::class);
        $reviewRepository->method('aggregate')->willReturnCallback(
            static function (Criteria $criteria, Context $context) use (&$capturedCriteria): AggregationResultCollection {
                $capturedCriteria = $criteria;

                return new AggregationResultCollection([new CountResult('review-count', 3)]);
            },
        );

        $helper = $this->createHelper(reviewRepository: $reviewRepository);

        $count = $helper->getReviewsCount($parent, $this->createContext());

        self::assertSame(3, $count);
        self::assertInstanceOf(Criteria::class, $capturedCriteria);
        self::assertTrue($this->reviewCriteriaTargetsFamilyId($capturedCriteria, $parentId));
    }

    public function testGetReviewsCountOnlyCountsApprovedReviews(): void
    {
        $product = new SalesChannelProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setParentId(null);

        $capturedCriteria = null;
        $reviewRepository = $this->createMock(EntityRepository::class);
        $reviewRepository->method('aggregate')->willReturnCallback(
            static function (Criteria $criteria, Context $context) use (&$capturedCriteria): AggregationResultCollection {
                $capturedCriteria = $criteria;

                return new AggregationResultCollection([new CountResult('review-count', 4)]);
            },
        );

        $helper = $this->createHelper(reviewRepository: $reviewRepository);

        $helper->getReviewsCount($product, $this->createContext());

        self::assertInstanceOf(Criteria::class, $capturedCriteria);
        // Mirrors Shopware's ratingAverage, which only averages reviews with status = 1.
        self::assertTrue($this->criteriaHasEqualsFilter($capturedCriteria, 'status', true));
    }

    private function reviewCriteriaTargetsFamilyId(Criteria $criteria, string $expectedId): bool
    {
        foreach ($criteria->getFilters() as $filter) {
            if (!$filter instanceof MultiFilter || $filter->getOperator() !== MultiFilter::CONNECTION_OR) {
                continue;
            }

            $matchedFields = [];
            foreach ($filter->getQueries() as $query) {
                if ($query instanceof EqualsFilter && $query->getValue() === $expectedId) {
                    $matchedFields[$query->getField()] = true;
                }
            }

            if (isset($matchedFields['product.id'], $matchedFields['product.parentId'])) {
                return true;
            }
        }

        return false;
    }

    private function createHelper(
        ?EntityRepository $productRepository = null,
        ?ConfigProvider $configProvider = null,
        ?SalesChannelRepository $salesChannelRepository = null,
        ?EntityRepository $reviewRepository = null,
    ): ProductHelper {
        $productRepository ??= $this->createMock(EntityRepository::class);
        $configProvider ??= $this->createMock(ConfigProvider::class);
        $productRoute = $this->createMock(AbstractProductDetailRoute::class);
        $reviewRepository ??= $this->createMock(EntityRepository::class);
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

    private function criteriaHasEqualsFilter(Criteria $criteria, string $field, mixed $value): bool
    {
        foreach ($criteria->getFilters() as $filter) {
            if ($filter instanceof EqualsFilter && $filter->getField() === $field && $filter->getValue() === $value) {
                return true;
            }
        }

        return false;
    }

    private function createDefinition(string $entityName): EntityDefinition
    {
        $definition = new class($entityName) extends EntityDefinition {
            public function __construct(
                private readonly string $entityName,
            ) {
            }

            public function getEntityName(): string
            {
                return $this->entityName;
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection();
            }
        };

        $definition->compile($this->createMock(DefinitionInstanceRegistry::class));

        return $definition;
    }
}
