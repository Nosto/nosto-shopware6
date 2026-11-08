<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Model\Operation;

use Nosto\Model\Product\Product as NostoProduct;
use Nosto\NostoIntegration\Enums\ProductIdentifierOptions;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Account;
use Nosto\NostoIntegration\Model\Nosto\Account\KeyChain;
use Nosto\NostoIntegration\Model\Nosto\Account\Provider as AccountProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProduct;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProductCollection;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProvider;
use Nosto\NostoIntegration\Model\Operation\ProductSyncHandler;
use Nosto\Scheduler\Model\Job\JobResult;
use Nosto\Scheduler\Model\Job\Message\WarningMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use Shopware\Core\Checkout\Cart\AbstractRuleLoader;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class ProductSyncHandlerTest extends TestCase
{
    #[DataProvider('provideValidateProductWarnings')]
    public function testValidateProductReturnsWarningsForMissingMandatoryFields(
        ?string $name,
        ?string $url,
        ?string $imageUrl,
        string $expectedMessage,
    ): void {
        $handler = $this->createHandler();
        $product = new NostoProduct();

        if ($name !== null) {
            $product->setName($name);
        }

        if ($url !== null) {
            $product->setUrl($url);
        }

        if ($imageUrl !== null) {
            $product->setImageUrl($imageUrl);
        }

        $result = $handler->validateProductPublic('SWDEMO10002', $product);

        self::assertInstanceOf(WarningMessage::class, $result);
        self::assertSame($expectedMessage, $result->getMessage());
    }

    public function testValidateProductReturnsNullWhenMandatoryFieldsArePresent(): void
    {
        $handler = $this->createHandler();
        $product = new NostoProduct();
        $product->setName('Example product');
        $product->setUrl('https://shop.example.test/product/example');
        $product->setImageUrl('https://cdn.example.test/product/example.jpg');

        self::assertNull($handler->validateProductPublic('SWDEMO10002', $product));
    }

    public function testCreateAccountContextDoesNotOverrideDomainOrCurrencyWhenMultiCurrencyIsEnabled(): void
    {
        $account = new Account('sales-channel-id', 'language-id', 'account', new KeyChain([]));
        $context = Context::createDefaultContext();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $contextFactory = $this->createMock(AbstractSalesChannelContextFactory::class);
        $contextFactory->expects($this->once())
            ->method('create')
            ->with(
                $this->isType('string'),
                'sales-channel-id',
                $this->callback(static fn (array $parameters): bool => $parameters === [
                    SalesChannelContextService::LANGUAGE_ID => 'language-id',
                ]),
            )
            ->willReturn($salesChannelContext);

        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isEnabledMultiCurrency')->willReturn(true);
        $configProvider->expects($this->never())->method('getDomainId');

        $handler = $this->createHandler(
            contextFactory: $contextFactory,
            configProvider: $configProvider,
        );

        self::assertSame($salesChannelContext, $handler->createAccountContextPublic($account, $context));
    }

    public function testCreateAccountContextIgnoresInvalidDomainWhenMultiCurrencyIsDisabled(): void
    {
        $account = new Account('sales-channel-id', 'language-id', 'account', new KeyChain([]));
        $context = Context::createDefaultContext();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $contextFactory = $this->createMock(AbstractSalesChannelContextFactory::class);
        $contextFactory->expects($this->once())
            ->method('create')
            ->with(
                $this->isType('string'),
                'sales-channel-id',
                $this->callback(static fn (array $parameters): bool => $parameters === [
                    SalesChannelContextService::LANGUAGE_ID => 'language-id',
                ]),
            )
            ->willReturn($salesChannelContext);

        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isEnabledMultiCurrency')->willReturn(false);
        $configProvider->method('getDomainId')->willReturn('invalid-domain-id');

        $domainRepository = $this->createMock(EntityRepository::class);
        $domainRepository->expects($this->never())->method('search');

        $handler = $this->createHandler(
            contextFactory: $contextFactory,
            domainRepository: $domainRepository,
            configProvider: $configProvider,
        );

        self::assertSame($salesChannelContext, $handler->createAccountContextPublic($account, $context));
    }

    public function testFlushDeleteOperationLoadsMissingProductNumberMappingsForQueuedDeletes(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);
        $salesChannelContext->method('getSalesChannelId')->willReturn('sales-channel-id');
        $salesChannelContext->method('getLanguageId')->willReturn('language-id');

        $productHelper = $this->createMock(ProductHelper::class);
        $productHelper->expects($this->once())
            ->method('loadOrderNumberMapping')
            ->with(['existing-product-id', 'queued-product-id'], $context)
            ->willReturn([
                'queued-product-id' => 'SW10001.2',
            ]);

        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('getProductIdentifier')->willReturn(ProductIdentifierOptions::PRODUCT_NUMBER);

        $handler = $this->createHandler(
            configProvider: $configProvider,
            productHelper: $productHelper,
        );

        $queuedDeleteIds = [
            'existing-product-id' => true,
            'queued-product-id' => true,
        ];
        $mapping = [
            'existing-product-id' => 'SW10001.1',
        ];

        $method = new ReflectionMethod(ProductSyncHandler::class, 'flushDeleteOperation');
        $method->invokeArgs($handler, [
            &$queuedDeleteIds,
            new Account('sales-channel-id', 'language-id', 'account', new KeyChain([])),
            $salesChannelContext,
            $mapping,
        ]);

        self::assertSame([], $queuedDeleteIds);
        self::assertSame(['existing-product-id', 'queued-product-id'], $handler->deletedProductIds);
        self::assertSame([
            'existing-product-id' => 'SW10001.1',
            'queued-product-id' => 'SW10001.2',
        ], $handler->deleteMapping);
        self::assertSame(['SW10001.1', 'SW10001.2'], $handler->deletedIdentifiers);
    }

    public function testHandleProductKeepsParentWhenCalculatedVariantAvailabilityIsInStock(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $product = new PartialProduct(new PartialEntity([
            'id' => 'parent-product-id',
            'parentId' => null,
            'children' => null,
        ]));

        $productHelper = $this->createMock(ProductHelper::class);
        $productHelper->expects(self::once())
            ->method('getProductStock')
            ->with($product, $context)
            ->willReturn(0);
        $productHelper->expects(self::once())
            ->method('isProductInStock')
            ->with($product, $context, 0)
            ->willReturn(true);

        $nostoProduct = new NostoProduct();
        $partialProductProvider = $this->createMock(PartialProvider::class);
        $partialProductProvider->expects(self::once())
            ->method('get')
            ->with($product, $context)
            ->willReturn($nostoProduct);

        $handler = $this->createHandler(
            productHelper: $productHelper,
            partialProductProvider: $partialProductProvider,
        );
        $queuedDeleteIds = [];

        self::assertSame(
            $nostoProduct,
            $handler->handleProductPublic($product, $context, true, $queuedDeleteIds),
        );
        self::assertSame([], $queuedDeleteIds);
    }

    public function testPreloadsSalesChannelCalculatedPricesInProductLoadBatches(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $productIds = array_map(
            static fn (int $index): string => 'product-' . $index,
            range(1, 51),
        );
        $loadedBatches = [];

        $productHelper = $this->createMock(ProductHelper::class);
        $productHelper->expects(self::exactly(2))
            ->method('getSalesChannelCalculatedPrices')
            ->willReturnCallback(static function (array $batch, SalesChannelContext $actualContext) use (
                &$loadedBatches,
                $context,
            ): array {
                self::assertSame($context, $actualContext);
                $loadedBatches[] = $batch;

                return [];
            });

        $handler = $this->createHandler(productHelper: $productHelper);
        $method = new ReflectionMethod(ProductSyncHandler::class, 'preloadSalesChannelCalculatedPricesBatched');
        $method->invoke($handler, $productIds, $context);

        self::assertSame([array_slice($productIds, 0, 50), array_slice($productIds, 50)], $loadedBatches);
    }

    public function testClearsCalculatedPriceCacheAfterEveryUpsertBatch(): void
    {
        $context = $this->createMock(SalesChannelContext::class);

        $existingProduct = new ProductEntity();
        $existingProduct->setId('product-id');
        $existingProducts = $this->createMock(EntitySearchResult::class);
        $existingProducts->method('getElements')->willReturn([
            'product-id' => $existingProduct,
        ]);

        $existingProductsIterator = $this->createMock(RepositoryIterator::class);
        $existingProductsIterator->method('fetch')->willReturnOnConsecutiveCalls($existingProducts, null);

        $firstBatch = $this->createMock(EntitySearchResult::class);
        $firstBatch->method('getEntities')->willReturn(new EntityCollection([
            new PartialEntity([
                'id' => 'product-id',
            ]),
        ]));
        $secondBatch = $this->createMock(EntitySearchResult::class);
        $secondBatch->method('getEntities')->willReturn(new EntityCollection([
            new PartialEntity([
                'id' => 'second-product-id',
            ]),
        ]));

        $parentProductsIterator = $this->createMock(RepositoryIterator::class);
        $parentProductsIterator->method('fetch')->willReturnOnConsecutiveCalls($firstBatch, $secondBatch, null);

        $productHelper = $this->createMock(ProductHelper::class);
        $productHelper->method('getProductsIterator')->willReturn($existingProductsIterator);
        $productHelper->method('loadExistingParentProducts')->willReturn($parentProductsIterator);
        $productHelper->expects(self::exactly(2))->method('clearCalculatedPriceCache');

        $handler = $this->createHandler(productHelper: $productHelper);
        $handler->skipUpsertOperation = true;

        $handler->doOperationPublic(
            new Account('sales-channel-id', 'language-id', 'account', new KeyChain([])),
            $context,
            [
                'product-id' => 'SW10001',
            ],
        );

        self::assertSame(2, $handler->upsertOperationCount);
    }

    /**
     * @return iterable<string, array{0: string|null, 1: string|null, 2: string|null, 3: string}>
     */
    public static function provideValidateProductWarnings(): iterable
    {
        yield 'missing image url' => [
            'Example product',
            'https://shop.example.test/product/example',
            null,
            'Product image url is empty, ignoring upsert for product with number. SWDEMO10002',
        ];

        yield 'missing url' => [
            'Example product',
            null,
            'https://cdn.example.test/product/example.jpg',
            'Product url is empty, ignoring upsert for product with number. SWDEMO10002',
        ];

        yield 'missing name' => [
            null,
            'https://shop.example.test/product/example',
            'https://cdn.example.test/product/example.jpg',
            'Product name is empty, ignoring upsert for product with number. SWDEMO10002',
        ];
    }

    private function createHandler(
        ?AbstractSalesChannelContextFactory $contextFactory = null,
        ?EntityRepository $domainRepository = null,
        ?ConfigProvider $configProvider = null,
        ?ProductHelper $productHelper = null,
        ?PartialProvider $partialProductProvider = null,
    ): TestableProductSyncHandler {
        return new TestableProductSyncHandler(
            $contextFactory ?? $this->createMock(AbstractSalesChannelContextFactory::class),
            $partialProductProvider ?? $this->createMock(PartialProvider::class),
            $this->createMock(AccountProvider::class),
            $domainRepository ?? $this->createMock(EntityRepository::class),
            $configProvider ?? $this->createMock(ConfigProvider::class),
            $this->createMock(AbstractRuleLoader::class),
            $productHelper ?? $this->createMock(ProductHelper::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(SystemConfigService::class),
            $this->createMock(LoggerInterface::class),
        );
    }
}

final class TestableProductSyncHandler extends ProductSyncHandler
{
    public bool $skipUpsertOperation = false;

    public int $upsertOperationCount = 0;

    /**
     * @var string[]
     */
    public array $deletedProductIds = [];

    /**
     * @var array<string, string>
     */
    public array $deleteMapping = [];

    /**
     * @var string[]
     */
    public array $deletedIdentifiers = [];

    public function validateProductPublic(string $productNumber, NostoProduct $product): ?WarningMessage
    {
        return $this->validateProduct($productNumber, $product);
    }

    public function createAccountContextPublic(Account $account, Context $context): SalesChannelContext
    {
        return $this->createAccountContext($account, $context);
    }

    /**
     * @param array<string, bool> $queuedDeleteIds
     */
    public function handleProductPublic(
        PartialProduct $product,
        SalesChannelContext $context,
        bool $hideProductsAfterClearance,
        array &$queuedDeleteIds,
    ): ?NostoProduct {
        return $this->handleProduct($product, $context, $hideProductsAfterClearance, $queuedDeleteIds);
    }

    /**
     * @param array<string, string> $ids
     */
    public function doOperationPublic(Account $account, SalesChannelContext $context, array $ids): JobResult
    {
        return $this->doOperation($account, $context, $ids);
    }

    protected function doUpsertOperation(
        Account $account,
        SalesChannelContext $context,
        PartialProductCollection $productCollection,
        JobResult $result,
        array $ids,
    ): void {
        if (!$this->skipUpsertOperation) {
            parent::doUpsertOperation($account, $context, $productCollection, $result, $ids);
            return;
        }

        ++$this->upsertOperationCount;
    }

    protected function doDeleteOperation(
        Account $account,
        SalesChannelContext $context,
        array $productIds,
        array $mapping,
    ): void {
        $this->deletedProductIds = $productIds;
        $this->deleteMapping = $mapping;
        $this->deletedIdentifiers = $this->getIdentifiers($context, $productIds, $mapping);
    }
}
