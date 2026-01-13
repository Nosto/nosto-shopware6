<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Operation;

use Nosto\Model\Product\Product as NostoProduct;
use Nosto\NostoException;
use Nosto\NostoIntegration\Async\ProductSyncMessage;
use Nosto\NostoIntegration\Enums\ProductIdentifierOptions;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Account;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\ProductProviderInterface;
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
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Rule\RuleEntity;
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
        private readonly ProductProviderInterface $productProvider,
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

        foreach ($this->accountProvider->all($message->getContext()) as $account) {
            $channelContext = $this->channelContextFactory->create(
                Uuid::randomHex(),
                $account->getChannelId(),
                [
                    SalesChannelContextService::LANGUAGE_ID => $account->getLanguageId(),
                ],
            );
            $channelContext->setRuleIds($this->loadRuleIds($channelContext));

            $accountOperationResult = $this->doOperation($account, $channelContext, $message->getProductIds());

            foreach ($accountOperationResult->getMessages() as $error) {
                $operationResult->addMessage($error);
            }
        }

        return $operationResult;
    }

    /**
     * @return string[]
     */
    protected function loadRuleIds(SalesChannelContext $channelContext): array
    {
        return $this->ruleLoader->load($channelContext->getContext())->filter(
            static fn (RuleEntity $rule) => $rule->getPayload()->match(new CheckoutRuleScope($channelContext)),
        )->getIds();
    }

    protected function doOperation(Account $account, SalesChannelContext $context, array $ids): Job\JobResult
    {
        $productIds = array_keys($ids);
        $result = new Job\JobResult();
        $existingProductsIterator = $this->productHelper->getProductsIterator($productIds, $context);
        $existentProducts = [];
        while (($existingProducts = $existingProductsIterator->fetch()) !== null) {
            foreach ($existingProducts->getElements() as $key => $product) {
                $existentProducts[$key] = $product->getParentId() ?: $product->getId();
            }
        }

        $deletedProductIds = array_diff($productIds, array_keys($existentProducts));

        $parentProductIterator = $this->productHelper->loadExistingParentProducts($existentProducts, $context);
        $shouldLogParentFetch = $this->shouldLogExtra($context);
        $parentFetchStartedAt = $shouldLogParentFetch ? microtime(true) : null;
        $processedParentCount = 0;

        while (($products = $parentProductIterator->fetch()) !== null) {
            foreach ($products->getEntities() as $product) {
                try {
                    $productCollection = new ProductCollection([$product]);

                    $this->doUpsertOperation($account, $context, $productCollection, $result, $ids);
                    ++$processedParentCount;
                } catch (\Throwable $e) {
                    $productId = $product->getId();
                    $productNumber = method_exists(
                        $product,
                        'getProductNumber',
                    ) ? $product->getProductNumber() : 'unknown';

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
        }

        if ($shouldLogParentFetch && $parentFetchStartedAt !== null) {
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

        return $result;
    }

    /**
     * @throws NostoException
     * @throws AbstractHttpException
     */
    protected function doUpsertOperation(
        Account $account,
        SalesChannelContext $context,
        ProductCollection $productCollection,
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
        $operation = new UpsertProduct($account->getNostoAccount(), $domain);
        $shouldLogExtra = $this->shouldLogExtra($context);

        $hideProductsAfterClearance = $this->systemConfigService->getBool(
            'core.listing.hideCloseoutProductsWhenOutOfStock',
            $channelId,
        );

        $productTaggingHelper = new ProductTaggingHelper(
            $this->systemConfigService,
            $this->configProvider,
            $this->productProvider,
            $this->productHelper,
        );

        /** @var ProductEntity $product */
        foreach ($productCollection as $product) {
            // TODO: up to 2MB payload!
            $nostoProducts = [];

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

            if (!$handledProducts->count()) {
                $this->deleteVariantProducts($product, $context, $account, $ids);
                $this->doDeleteOperation(
                    $account,
                    $context,
                    [$product->getId(), $product->getParentId()],
                    $ids,
                );
            }

            $shopwareProducts = $handledProducts->count()
                ? $this->productHelper->getShopwareProducts($handledProducts->getIds(), $context)
                : new ProductCollection();

            foreach ($handledProducts as $handledProduct) {
                $shopwareProduct = $shopwareProducts->get($handledProduct->getId());
                $productStartedAt = $shouldLogExtra ? microtime(true) : null;
                $handledResult = 'skipped';

                if ($shopwareProduct) {
                    $shopwareProduct->setChildren($handledProduct->getChildren());
                    $nostoProduct = $this->handleProduct(
                        $shopwareProduct,
                        $context,
                        $account,
                        $hideProductsAfterClearance,
                        $ids,
                    );

                    if ($nostoProduct) {
                        $nostoProducts[] = $nostoProduct;
                        $handledResult = 'prepared';
                    }
                } else {
                    $this->deleteVariantProducts($handledProduct, $context, $account, $ids);
                    $this->doDeleteOperation(
                        $account,
                        $context,
                        [$handledProduct->getId(), $handledProduct->getParentId()],
                        $ids,
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

                $operation->addProduct($preparedProductForSync);
            }
        }

        $this->eventDispatcher->dispatch(new BeforeUpsertProductsEvent($operation, $context->getContext()));
        $operation->upsert();
    }

    protected function handleProduct(
        SalesChannelProductEntity $product,
        SalesChannelContext $context,
        Account $account,
        bool $hideProductsAfterClearance,
        array $mapping,
    ): ?NostoProduct {
        $stock = $this->productHelper->getProductStock($product, $context);

        if ($product->getChildren()?->count()) {
            $this->deleteVariantProducts($product, $context, $account, $mapping);
        }

        if ($product->getParentId()) {
            $this->doDeleteOperation($account, $context, [$product->getParentId()], $mapping);
        }

        if ($hideProductsAfterClearance && $product->getIsCloseout() && $stock < 1) {
            $this->doDeleteOperation($account, $context, [$product->getId()], $mapping);
            return null;
        }

        return $this->productProvider->get($product, $context);
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
        SalesChannelProductEntity|ProductEntity $product,
        SalesChannelContext $context,
        Account $account,
        array $mapping,
    ): void {
        $idsToDelete = [];

        foreach ($product->getChildren() as $prod) {
            $idsToDelete[] = $prod->getId();
        }

        $this->doDeleteOperation($account, $context, $idsToDelete, $mapping);
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
        $identifiers = $this->getIdentifiers($context, $productIds, $mapping);
        $domainUrl = $this->getDomainUrl(
            $context->getSalesChannel()->getDomains(),
            $context->getSalesChannelId(),
            $context->getLanguageId(),
        );
        $domain = parse_url($domainUrl, PHP_URL_HOST);

        $operation = new DeleteProduct($account->getNostoAccount(), $domain);
        $operation->setProductIds($identifiers);
        $this->eventDispatcher->dispatch(new BeforeDeleteProductsEvent($operation, $context->getContext()));
        $operation->delete();
    }

    /**
     * @param string[] $productIds
     * @param array<string, string> $mapping
     *
     * @return string[]
     */
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

    /**
     * @param string[] $productIds
     * @param array<string, string> $mapping
     * @return string[]
     */
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
