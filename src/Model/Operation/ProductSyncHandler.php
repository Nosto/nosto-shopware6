<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Operation;

use Nosto\Helper\SerializationHelper;
use Nosto\Model\Product\Product as NostoProduct;
use Nosto\NostoException;
use Nosto\NostoIntegration\Async\ProductSyncMessage;
use Nosto\NostoIntegration\Enums\ProductIdentifierOptions;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Account;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProduct;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProductCollection;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProductConverter;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProvider;
use Nosto\NostoIntegration\Model\Operation\Event\BeforeDeleteProductsEvent;
use Nosto\NostoIntegration\Model\Operation\Event\BeforeUpsertProductsEvent;
use Nosto\NostoIntegration\Utils\ProductTaggingHelper;
use Nosto\Operation\DeleteProduct;
use Nosto\Operation\UpsertProduct;
use Nosto\Request\Http\Exception\AbstractHttpException;
use Nosto\Scheduler\Model\Job;
use Nosto\Scheduler\Model\Job\Message\WarningMessage;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\AbstractRuleLoader;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ProductSyncHandler implements Job\JobHandlerInterface
{
    public const HANDLER_CODE = 'nosto-integration-product-sync';

    public function __construct(
        private readonly AbstractSalesChannelContextFactory $channelContextFactory,
        private readonly PartialProvider $partialProductProvider,
        private readonly Account\Provider $accountProvider,
        private readonly ConfigProvider $configProvider,
        private readonly AbstractRuleLoader $ruleLoader,
        private readonly ProductHelper $productHelper,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @var array<string, bool>
     */
    private array $extraLoggingCache = [];

    /**
     * @param ProductSyncMessage $message
     */
    public function execute(object $message): Job\JobResult
    {
        $operationResult = new Job\JobResult();
        $shouldLogExtra = null;
        $jobStartedAt = null;

        $accounts = $this->resolveAccounts($message);

        foreach ($accounts as $account) {
            $channelContext = $this->channelContextFactory->create(
                Uuid::randomHex(),
                $account->getChannelId(),
                [
                    SalesChannelContextService::LANGUAGE_ID => $account->getLanguageId(),
                ],
            );
            $channelContext->setRuleIds($this->loadRuleIds($channelContext));

            if ($shouldLogExtra === null) {
                $shouldLogExtra = $this->shouldLogExtra($channelContext);
                $jobStartedAt = $shouldLogExtra ? microtime(true) : null;
            }
            $accountOperationResult = $this->doOperation($account, $channelContext, $message->getProductIds());

            foreach ($accountOperationResult->getMessages() as $error) {
                $operationResult->addMessage($error);
            }
        }

        if ($shouldLogExtra && $jobStartedAt !== null && $accounts !== []) {
            $this->logDuration(
                $channelContext,
                'product_sync.execute.job',
                $jobStartedAt,
                [
                    'account_count' => count($accounts),
                    'product_count' => count($message->getProductIds()),
                ],
            );
        }

        return $operationResult;
    }

    private function resolveAccounts(ProductSyncMessage $message): array
    {
        $context = $message->getContext();
        $channelId = $message->getChannelId();
        $languageId = $message->getLanguageId();

        if ($channelId && $languageId) {
            $account = $this->accountProvider->get($context, $channelId, $languageId);
            return $account ? [$account] : [];
        }

        return $this->accountProvider->all($context);
    }

    protected function loadRuleIds(SalesChannelContext $channelContext): array
    {
        $shouldLogExtra = $this->shouldLogExtra($channelContext);
        $loadRulesStartedAt = $shouldLogExtra ? microtime(true) : null;
        $ruleIds = $this->ruleLoader->load($channelContext->getContext())->filter(
            function (RuleEntity $rule) use ($channelContext) {
                return $rule->getPayload()->match(new CheckoutRuleScope($channelContext));
            },
        )->getIds();
        if ($shouldLogExtra && $loadRulesStartedAt !== null) {
            $this->logDuration(
                $channelContext,
                'product_sync.loadRuleIds',
                $loadRulesStartedAt,
                [
                    'rule_count' => count($ruleIds),
                ],
            );
        }

        return $ruleIds;
    }

    protected function doOperation(Account $account, SalesChannelContext $context, array $ids): Job\JobResult
    {
        $shouldLogExtra = $this->shouldLogExtra($context);
        $operationStartedAt = $shouldLogExtra ? microtime(true) : null;
        $productIds = array_keys($ids);
        $result = new Job\JobResult();
        $productsFetchStartedAt = $shouldLogExtra ? microtime(true) : null;
        $existingProductsIterator = $this->productHelper->getProductsIterator($productIds, $context);
        $existentProducts = [];
        while (($existingProducts = $existingProductsIterator->fetch()) !== null) {
            foreach ($existingProducts->getElements() as $key => $product) {
                $existentProducts[$key] = $product->getParentId() ?: $product->getId();
            }
        }
        if ($shouldLogExtra && $productsFetchStartedAt !== null) {
            $this->logDuration(
                $context,
                'product_sync.getProductsIterator.fetch',
                $productsFetchStartedAt,
                [
                    'requested_count' => count($productIds),
                    'existing_count' => count($existentProducts),
                ],
            );
        }

        $deletedProductIds = array_diff($productIds, array_keys($existentProducts));

        $parentProductIterator = $this->productHelper->loadExistingParentProducts($existentProducts, $context);
        $parentFetchStartedAt = $shouldLogExtra ? microtime(true) : null;
        $processedParentCount = 0;

        while (($products = $parentProductIterator->fetch()) !== null) {
            $partialProductsCollection = PartialProductConverter::toPartialProductCollection($products->getEntities());
            $this->doUpsertOperation($account, $context, $partialProductsCollection, $result, $ids);
            $processedParentCount += $partialProductsCollection->count();
        }

        if ($shouldLogExtra && $parentFetchStartedAt !== null) {
            $this->logDuration(
                $context,
                'product_sync.loadExistingParentProducts.fetch',
                $parentFetchStartedAt,
                [
                    'parent_count' => $processedParentCount,
                ],
            );
        }

        if (!empty($deletedProductIds)) {
            try {
                $this->doDeleteOperation($account, $context, $deletedProductIds, $ids);
            } catch (\Throwable $e) {
                $wrappedException = new \RuntimeException(
                    'Error during product deletion: ' . $e->getMessage(),
                    0,
                    $e,
                );
                $result->addError($wrappedException);
            }
        }

        if ($shouldLogExtra && $operationStartedAt !== null) {
            $this->logDuration(
                $context,
                'product_sync.doOperation',
                $operationStartedAt,
                [
                    'product_count' => count($productIds),
                    'deleted_count' => count($deletedProductIds),
                ],
            );
        }

        return $result;
    }

    /**
     * @throws NostoException
     * @throws AbstractHttpException
     */
    protected function doUpsertOperation(
        Account $account,
        SalesChannelContext $context,
        PartialProductCollection $productCollection,
        Job\JobResult $result,
        array $ids,
    ): void {
        $channelId = $context->getSalesChannelId();
        $languageId = $context->getLanguageId();
        $domainUrl = $this->getDomainUrl(
            $context->getSalesChannel()->getDomains(),
            $channelId,
            $languageId,
        );
        $domain = parse_url($domainUrl, PHP_URL_HOST);
        $shouldLogExtra = $this->shouldLogExtra($context);

        $hideProductsAfterClearance = $this->systemConfigService->getBool(
            'core.listing.hideCloseoutProductsWhenOutOfStock',
            $channelId,
        );

        $productTaggingHelper = new ProductTaggingHelper(
            $this->systemConfigService,
            $this->configProvider,
            $this->partialProductProvider,
            $this->productHelper,
        );

        // up to 2MB payload!
        $maxPayloadBytes = 2 * 1024 * 1024;
        $operationPayloadBytes = 2;
        $operationProductCount = 0;
        $operationProducts = [];
        /** @var array<string, bool> */
        $queuedDeleteIds = [];

        // === Pass 1: Collect handled products (in-memory, no DB calls) ===
        /** @var array<string, PartialProductCollection> */
        $handledMap = [];
        $allUniqueIds = [];
        $failedProductIds = [];
        $collectStartedAt = $shouldLogExtra ? microtime(true) : null;

        /** @var PartialProduct $product */
        foreach ($productCollection as $product) {
            try {
                $findProductIdStartedAt = $shouldLogExtra ? microtime(true) : null;
                $handledProducts = $productTaggingHelper->findProductId(
                    $context,
                    $product,
                    null,
                    true,
                    true,
                );

                if ($shouldLogExtra && $findProductIdStartedAt !== null) {
                    $this->logDuration(
                        $context,
                        'product_sync.productTaggingHelper.findProductId',
                        $findProductIdStartedAt,
                        [
                            'product_id' => $product->getId(),
                            'handled_count' => $handledProducts->count(),
                        ],
                    );
                }

                $handledMap[$product->getId()] = $handledProducts;

                if (!$handledProducts->count()) {
                    $this->deleteVariantProducts($product, $queuedDeleteIds);
                    $this->queueDeleteProductIds($queuedDeleteIds, [$product->getId(), $product->getParentId()]);
                } else {
                    foreach ($handledProducts->getIds() as $id) {
                        $allUniqueIds[$id] = true;
                    }
                }
            } catch (\Throwable $e) {
                $failedProductIds[$product->getId()] = true;
                $productId = $product->getId();
                $productNumber = method_exists($product, 'getProductNumber') ? $product->getProductNumber() : 'unknown';
                $message = sprintf(
                    'Error while processing product ID: %s (Product Number: %s): %s',
                    $productId,
                    $productNumber,
                    $e->getMessage(),
                );

                $wrappedException = new \RuntimeException($message, 0, $e);
                $result->addError($wrappedException);
            }
        }

        if ($shouldLogExtra && $collectStartedAt !== null) {
            $this->logDuration(
                $context,
                'product_sync.collectHandledProducts',
                $collectStartedAt,
                [
                    'product_count' => $productCollection->count(),
                    'unique_handled_ids' => count($allUniqueIds),
                    'failed_count' => count($failedProductIds),
                ],
            );
        }

        // === Pass 2: Single batched DB query ===
        $shopwareProductsFetchStartedAt = $shouldLogExtra ? microtime(true) : null;
        $allShopwareProducts = !empty($allUniqueIds)
            ? (PartialProductConverter::toPartialProductCollection(
                $this->productHelper->getShopwareProductsPartial(array_keys($allUniqueIds), $context),
            ))
            : new PartialProductCollection();
        /** @var array<string, PartialProduct> $allShopwareProductsById */
        $allShopwareProductsById = [];
        foreach ($allShopwareProducts as $shopwareProduct) {
            $allShopwareProductsById[$shopwareProduct->getId()] = $shopwareProduct;
        }
        if ($shouldLogExtra && $shopwareProductsFetchStartedAt !== null) {
            $this->logDuration(
                $context,
                'product_sync.getShopwareProducts.batch',
                $shopwareProductsFetchStartedAt,
                [
                    'requested_count' => count($allUniqueIds),
                    'loaded_count' => $allShopwareProducts->count(),
                ],
            );
        }

        // === Pass 3: Process using pre-loaded data ===
        /** @var PartialProduct $product */
        foreach ($productCollection as $product) {
            if (isset($failedProductIds[$product->getId()])) {
                continue;
            }

            try {
                $nostoProducts = [];
                $handledProducts = $handledMap[$product->getId()];
                if ($handledProducts instanceof EntityCollection && !$handledProducts instanceof PartialProductCollection) {
                    $handledProducts = PartialProductConverter::toPartialProductCollection($handledProducts);
                }

                foreach ($handledProducts as $handledProduct) {
                    if ($handledProduct instanceof PartialEntity && !$product instanceof PartialProduct) {
                        $handledProduct = PartialProductConverter::toPartialProduct($handledProduct);
                    }

                    $shopwareProduct = $allShopwareProductsById[$handledProduct->getId()] ?? null;
                    $productStartedAt = $shouldLogExtra ? microtime(true) : null;
                    $handledResult = 'skipped';

                    if ($shopwareProduct) {
                        $nostoProduct = $this->handleProduct(
                            $shopwareProduct,
                            $context,
                            $hideProductsAfterClearance,
                            $queuedDeleteIds,
                        );

                        if ($nostoProduct) {
                            $nostoProducts[] = $nostoProduct;
                            $handledResult = 'prepared';
                        }
                    } else {
                        $this->deleteVariantProducts($handledProduct, $queuedDeleteIds);
                        $this->queueDeleteProductIds(
                            $queuedDeleteIds,
                            [$handledProduct->getId(), $handledProduct->getParentId()],
                        );
                        $handledResult = 'deleted';
                    }

                    if ($shouldLogExtra && $productStartedAt !== null) {
                        $this->logDuration(
                            $context,
                            'product_sync.product_handle',
                            $productStartedAt,
                            [
                                'product_id' => $handledProduct->getId(),
                                'result' => $handledResult,
                            ],
                        );
                    }
                }

                foreach ($nostoProducts as $preparedProductForSync) {
                    $invalidMessage = $this->validateProduct(
                        $preparedProductForSync->getProductId(),
                        $preparedProductForSync,
                    );

                    if ($invalidMessage) {
                        $result->addMessage($invalidMessage);
                        continue;
                    }

                    $productPayloadJson = SerializationHelper::serialize($preparedProductForSync);
                    $productPayloadSize = strlen($productPayloadJson);
                    $nextPayloadBytes = $operationPayloadBytes
                        + ($operationProductCount > 0 ? 1 : 0)
                        + $productPayloadSize;
                    if ($operationProductCount > 0 && $nextPayloadBytes > $maxPayloadBytes) {
                        $this->flushDeleteOperation($queuedDeleteIds, $account, $context, $ids);
                        $this->flushUpsertOperation(
                            $operationProducts,
                            $operationPayloadBytes,
                            $operationProductCount,
                            $account,
                            $context,
                            $domain,
                            $shouldLogExtra,
                            $maxPayloadBytes,
                        );
                        $nextPayloadBytes = 2 + $productPayloadSize;
                    }

                    $operationProducts[] = [
                        'product' => $preparedProductForSync,
                        'json' => $productPayloadJson,
                    ];
                    $operationPayloadBytes = $nextPayloadBytes;
                    $operationProductCount++;
                }
            } catch (\Throwable $e) {
                $productId = $product->getId();
                $productNumber = method_exists($product, 'getProductNumber') ? $product->getProductNumber() : 'unknown';
                $message = sprintf(
                    'Error while processing product ID: %s (Product Number: %s): %s',
                    $productId,
                    $productNumber,
                    $e->getMessage(),
                );

                $wrappedException = new \RuntimeException($message, 0, $e);
                $result->addError($wrappedException);
            }
        }

        $this->flushDeleteOperation($queuedDeleteIds, $account, $context, $ids);
        $this->flushUpsertOperation(
            $operationProducts,
            $operationPayloadBytes,
            $operationProductCount,
            $account,
            $context,
            $domain,
            $shouldLogExtra,
            $maxPayloadBytes,
        );
    }

    protected function handleProduct(
        PartialProduct $product,
        SalesChannelContext $context,
        bool $hideProductsAfterClearance,
        array &$queuedDeleteIds,
    ): ?NostoProduct {
        $stock = $this->productHelper->getProductStock($product, $context);

        if ($product->getChildren()?->count()) {
            $this->deleteVariantProducts($product, $queuedDeleteIds);
        }

        if ($product->getParentId()) {
            $this->queueDeleteProductIds($queuedDeleteIds, [$product->getParentId()]);
        }

        if ($hideProductsAfterClearance && $product->getIsCloseout() && $stock < 1) {
            $this->queueDeleteProductIds($queuedDeleteIds, [$product->getId()]);
            return null;
        }

        return $this->partialProductProvider->get($product, $context);
    }

    /**
     * @param array<string, bool> $queuedDeleteIds
     * @param array<string|null> $productIds
     */
    private function queueDeleteProductIds(array &$queuedDeleteIds, array $productIds): void
    {
        foreach ($productIds as $productId) {
            if ($productId) {
                $queuedDeleteIds[$productId] = true;
            }
        }
    }

    /**
     * @param array<string, bool> $queuedDeleteIds
     * @param array<string, string> $mapping
     */
    private function flushDeleteOperation(
        array &$queuedDeleteIds,
        Account $account,
        SalesChannelContext $context,
        array $mapping,
    ): void {
        if (!$queuedDeleteIds) {
            return;
        }

        $this->doDeleteOperation($account, $context, array_keys($queuedDeleteIds), $mapping);
        $queuedDeleteIds = [];
    }

    /**
     * @param array<int, array{product: NostoProduct, json: string}> $operationProducts
     */
    private function flushUpsertOperation(
        array &$operationProducts,
        int &$operationPayloadBytes,
        int &$operationProductCount,
        Account $account,
        SalesChannelContext $context,
        string $domain,
        bool $shouldLogExtra,
        int $maxPayloadBytes,
    ): void {
        if (!$operationProducts) {
            return;
        }

        $this->sendUpsertBatch(
            $operationProducts,
            $account,
            $context,
            $domain,
            $shouldLogExtra,
            $maxPayloadBytes,
        );
        $operationProducts = [];
        $operationPayloadBytes = 2;
        $operationProductCount = 0;
    }

    /**
     * @param array<int, array{product: NostoProduct, json: string}> $products
     */
    private function sendUpsertBatch(
        array $products,
        Account $account,
        SalesChannelContext $context,
        string $domain,
        bool $shouldLogExtra,
        int $maxPayloadBytes,
    ): void {
        if (!$products) {
            return;
        }

        $operation = new UpsertProduct($account->getNostoAccount(), $domain);
        $operationProductIds = [];
        $payloadChunks = [];

        foreach ($products as $entry) {
            $product = $entry['product'];
            $operation->addProduct($product);
            $operationProductIds[] = $product->getProductId();
            $payloadChunks[] = $entry['json'];
        }

        $payloadJson = '[' . implode(',', $payloadChunks) . ']';
        $payloadSize = strlen($payloadJson);
        if ($payloadSize > $maxPayloadBytes && count($products) > 1) {
            $splitIndex = intdiv(count($products), 2);
            $this->sendUpsertBatch(
                array_slice($products, 0, $splitIndex),
                $account,
                $context,
                $domain,
                $shouldLogExtra,
                $maxPayloadBytes,
            );
            $this->sendUpsertBatch(
                array_slice($products, $splitIndex),
                $account,
                $context,
                $domain,
                $shouldLogExtra,
                $maxPayloadBytes,
            );
            return;
        }

        $dispatchStartedAt = $shouldLogExtra ? microtime(true) : null;
        $this->eventDispatcher->dispatch(new BeforeUpsertProductsEvent($operation, $context->getContext()));
        if ($shouldLogExtra && $dispatchStartedAt !== null) {
            $this->logDuration(
                $context,
                'product_sync.beforeUpsertProductsEvent',
                $dispatchStartedAt,
            );
        }

        if ($shouldLogExtra) {
            $this->logger->info('product_sync.upsert.request', [
                'product_count' => count($operationProductIds),
                'product_ids' => $operationProductIds,
                'payload_size_bytes' => $payloadSize,
                'sales_channel_id' => $context->getSalesChannelId(),
                'language_id' => $context->getLanguageId(),
                'domain' => $domain,
            ]);
        }

        $upsertStartedAt = $shouldLogExtra ? microtime(true) : null;
        try {
            $upsertResult = $operation->upsert();
        } catch (\Throwable $e) {
            if ($shouldLogExtra) {
                $this->logger->error('product_sync.upsert.response', [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'product_count' => count($operationProductIds),
                    'sales_channel_id' => $context->getSalesChannelId(),
                    'language_id' => $context->getLanguageId(),
                    'domain' => $domain,
                    'payload' => $payloadJson,
                    'payload_size_bytes' => $payloadSize,
                ]);
            }
            throw $e;
        }
        if ($shouldLogExtra) {
            $this->logger->info('product_sync.upsert.response', [
                'success' => $upsertResult,
                'product_count' => count($operationProductIds),
                'sales_channel_id' => $context->getSalesChannelId(),
                'language_id' => $context->getLanguageId(),
                'domain' => $domain,
            ]);
        }
        if ($shouldLogExtra && $upsertStartedAt !== null) {
            $this->logDuration(
                $context,
                'product_sync.upsert',
                $upsertStartedAt,
            );
        }
    }

    private function shouldLogExtra(SalesChannelContext $context): bool
    {
        $cacheKey = sprintf('%s-%s', $context->getSalesChannelId(), $context->getLanguageId());
        if (!array_key_exists($cacheKey, $this->extraLoggingCache)) {
            $this->extraLoggingCache[$cacheKey] = $this->configProvider->isEnabledProductSyncExtraLogging(
                $context->getSalesChannelId(),
                $context->getLanguageId(),
            );
        }

        return $this->extraLoggingCache[$cacheKey];
    }

    private function logDuration(
        SalesChannelContext $context,
        string $message,
        float $startTime,
        array $additionalContext = [],
    ): void {
        $durationMs = (microtime(true) - $startTime) * 1000;

        $this->logger->info($message, array_merge(
            $additionalContext,
            [
                'duration_ms' => round($durationMs, 2),
                'sales_channel_id' => $context->getSalesChannelId(),
                'language_id' => $context->getLanguageId(),
            ],
        ));
    }

    protected function deleteVariantProducts(
        SalesChannelProductEntity|ProductEntity|PartialProduct $product,
        array &$queuedDeleteIds,
    ): void {
        $children = $product->getChildren();
        if (!$children || !$children->count()) {
            return;
        }

        foreach ($children as $prod) {
            $id = $prod->getId();
            if ($id) {
                $queuedDeleteIds[$id] = true;
            }
        }
    }

    protected function validateProduct(string $productNumber, NostoProduct $product): ?Job\JobRuntimeMessageInterface
    {
        $message = '';

        if (!$product->getImageUrl()) {
            $message .= 'Product image url is empty, ';
        }

        if (!$product->getUrl()) {
            $message .= 'Product url is empty, ';
        }

        if (!$product->getName()) {
            $message .= 'Product name is empty, ';
        }

        return empty($message) ? null : new WarningMessage(
            $message . 'ignoring upsert for product with number. ' . $productNumber,
        );
    }

    protected function doDeleteOperation(
        Account $account,
        SalesChannelContext $context,
        array $productIds,
        array $mapping,
    ): void {
        $shouldLogExtra = $this->shouldLogExtra($context);
        $identifiersStartedAt = $shouldLogExtra ? microtime(true) : null;
        $identifiers = $this->getIdentifiers($context, $productIds, $mapping);
        if ($shouldLogExtra && $identifiersStartedAt !== null) {
            $this->logDuration(
                $context,
                'product_sync.getIdentifiers',
                $identifiersStartedAt,
                [
                    'product_count' => count($productIds),
                    'identifier_count' => count($identifiers),
                ],
            );
        }
        $domainUrl = $this->getDomainUrl(
            $context->getSalesChannel()->getDomains(),
            $context->getSalesChannelId(),
            $context->getLanguageId(),
        );
        $domain = parse_url($domainUrl, PHP_URL_HOST);

        $operation = new DeleteProduct($account->getNostoAccount(), $domain);
        $operation->setProductIds($identifiers);
        $dispatchStartedAt = $shouldLogExtra ? microtime(true) : null;
        $this->eventDispatcher->dispatch(new BeforeDeleteProductsEvent($operation, $context->getContext()));
        if ($shouldLogExtra && $dispatchStartedAt !== null) {
            $this->logDuration(
                $context,
                'product_sync.beforeDeleteProductsEvent',
                $dispatchStartedAt,
            );
        }

        $deleteStartedAt = $shouldLogExtra ? microtime(true) : null;
        $operation->delete();
        if ($shouldLogExtra && $deleteStartedAt !== null) {
            $this->logDuration(
                $context,
                'product_sync.delete',
                $deleteStartedAt,
            );
        }
    }

    protected function getIdentifiers(SalesChannelContext $context, array $productIds, array $mapping): array
    {
        $identifierType = $this->configProvider->getProductIdentifier(
            $context->getSalesChannelId(),
            $context->getLanguageId(),
        );
        if ($identifierType === ProductIdentifierOptions::PRODUCT_NUMBER) {
            return $this->getProductNumbers($productIds, $mapping);
        }

        return $productIds;
    }

    protected function getProductNumbers(array $productIds, array $mapping): array
    {
        $productNumbers = [];

        foreach ($productIds as $productId) {
            if (!empty($mapping[$productId])) {
                $productNumbers[] = $mapping[$productId];
            }
        }

        return $productNumbers;
    }

    protected function getDomainUrl(
        ?SalesChannelDomainCollection $domains,
        ?string $channelId,
        ?string $languageId,
    ): string {
        if ($domains == null || $domains->count() < 1) {
            return '';
        }

        $domainId = (string) $this->configProvider->getDomainId($channelId, $languageId);

        return $domains->has($domainId) ? $domains->get($domainId)->getUrl() : $domains->first()->getUrl();
    }
}
