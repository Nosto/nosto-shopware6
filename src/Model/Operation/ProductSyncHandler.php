<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Operation;

use Nosto\Model\Product\Product as NostoProduct;
use Nosto\NostoException;
use Nosto\NostoIntegration\Async\ProductSyncMessage;
use Nosto\NostoIntegration\Decorator\Core\Content\Product\DataAbstractionLayer\VariantListingConfig;
use Nosto\NostoIntegration\Enums\ProductIdentifierOptions;
use Nosto\NostoIntegration\Enums\StockFieldOptions;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Account;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\ProductProviderInterface;
use Nosto\NostoIntegration\Model\Operation\Event\BeforeDeleteProductsEvent;
use Nosto\NostoIntegration\Model\Operation\Event\BeforeUpsertProductsEvent;
use Nosto\Operation\DeleteProduct;
use Nosto\Operation\UpsertProduct;
use Nosto\Request\Http\Exception\AbstractHttpException;
use Nosto\Scheduler\Model\Job;
use Nosto\Scheduler\Model\Job\Message\WarningMessage;
use Shopware\Core\Checkout\Cart\AbstractRuleLoader;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

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
        private readonly SalesChannelRepository $salesChannelProductRepository,
    ) {
    }

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

    private function loadRuleIds(SalesChannelContext $channelContext): array
    {
        return $this->ruleLoader->load($channelContext->getContext())->filter(
            function (RuleEntity $rule) use ($channelContext) {
                return $rule->getPayload()->match(new CheckoutRuleScope($channelContext));
            },
        )->getIds();
    }

    private function doOperation(Account $account, SalesChannelContext $context, array $ids): Job\JobResult
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

        try {
            while (($products = $parentProductIterator->fetch()) !== null) {
                $this->doUpsertOperation($account, $context, $products->getEntities(), $result, $ids);
            }

            if (!empty($deletedProductIds)) {
                $this->doDeleteOperation($account, $context, $deletedProductIds, $ids);
            }
        } catch (Throwable $e) {
            $result->addError($e);
        }

        return $result;
    }

    /**
     * @throws NostoException
     * @throws AbstractHttpException
     */
    private function doUpsertOperation(
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

        $hideProductsAfterClearance = $this->systemConfigService->getBool(
            'core.listing.hideCloseoutProductsWhenOutOfStock',
            $channelId,
        );

        /** @var ProductEntity $product */
        foreach ($productCollection as $product) {
            // TODO: up to 2MB payload!
            $nostoProducts = [];
            $handledProducts = $this->processProductVariants($product, $context, $account, $ids);
            $shopwareProducts = $handledProducts->count()
                ? $this->getShopwareProducts($handledProducts->getIds(), $context)
                : new ProductCollection();

            foreach ($handledProducts as $handledProduct) {
                $shopwareProduct = $shopwareProducts->get($handledProduct->getId());

                if ($shopwareProduct) {
                    $shopwareProduct->setChildren($handledProduct->getChildren());
                    $nostoProducts[] = $this->handleProduct(
                        $shopwareProduct,
                        $context,
                        $account,
                        $hideProductsAfterClearance,
                        $ids,
                    );
                } else {
                    $this->deleteVariantProducts($handledProduct, $context, $account, $ids);
                    $this->doDeleteOperation(
                        $account,
                        $context,
                        [$handledProduct->getId(), $handledProduct->getParentId()],
                        $ids
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

    private function processProductVariants(
        ProductEntity $product,
        SalesChannelContext $context,
        Account $account,
        array $ids,
    ): ProductCollection {
        $variantConfig = $product->getVariantListingConfig();
        $configuratorGroups = array_filter(
            $variantConfig?->getConfiguratorGroupConfig() ?? [],
            static fn (array $config) => $config['expressionForListings'],
        );

        if (!$product->getChildCount() || !($variantConfig instanceof VariantListingConfig)) {
            return new ProductCollection([$product]);
        }

        $mainProducts = new ProductCollection();
        if ($variantConfig->getDisplayCheapestVariant()) {
            $mainProducts->merge($this->handleCheapestVariant($product, $context));
        } elseif ($variantConfig->getMainVariantId()) {
            if ($variant = $this->handleMainVariant($product, $variantConfig)) {
                $mainProducts->merge($variant);
            } elseif ($variant = $this->handleFirstActiveVariant($product)) {
                $mainProducts->merge($variant);
            }
        } elseif (count($configuratorGroups)) {
            $mainProducts->merge($this->handleConfiguratorGroups($product));
        } elseif (!$variantConfig->getDisplayParent() || !$product->getActive()) {
            if ($variant = $this->handleFirstActiveVariant($product)) {
                $mainProducts->merge($variant);
            }
        }

        if (!$mainProducts->count()) {
            $this->deleteVariantProducts($product, $context, $account, $ids);
            $this->doDeleteOperation(
                $account,
                $context,
                [$product->getId(), $product->getParentId()],
                $ids
            );
        }

        return $mainProducts;
    }

    private function handleCheapestVariant(
        ProductEntity $product,
        SalesChannelContext $context,
    ): ProductCollection {
        $cheapestVariant = $product;
        $lowestPrice = null;

        foreach ($product->getChildren() as $child) {
            $variantPrice = $child->getCurrencyPrice($context->getCurrencyId())->getNet();

            if ((is_null($lowestPrice) || $variantPrice < $lowestPrice) && $child->getActive()) {
                $lowestPrice = $variantPrice;
                $cheapestVariant = $child;
            }
        }

        $cheapestVariant->setChildren(
            $product->getChildren()->filter(
                static fn (ProductEntity $child) => $child->getId() !== $cheapestVariant->getId(),
            ),
        );

        return new ProductCollection([$cheapestVariant]);
    }

    private function handleMainVariant(
        ProductEntity $product,
        VariantListingConfig $variantConfig,
    ): ?ProductCollection {
        $mainProduct = null;
        $variants = new ProductCollection([$product]);

        foreach ($product->getChildren() as $child) {
            if ($child->getId() === $variantConfig->getMainVariantId() && $child->getActive()) {
                $mainProduct = $child;
            } else {
                $variants->add($child);
            }
        }

        if (!$mainProduct) {
            return null;
        }

        $mainProduct->setChildren($variants);

        return new ProductCollection([$mainProduct]);
    }

    private function handleConfiguratorGroups(ProductEntity $product): ProductCollection
    {
        $groupedVariants = [];
        foreach ($product->getChildren() as $child) {
            $groupedVariants[$child->getDisplayGroup()][$child->getId()] = $child;
        }

        $mainProducts = new ProductCollection();
        foreach ($groupedVariants as $variants) {
            /** @var SalesChannelProductEntity $mainProduct */
            $mainProduct = array_shift($variants);
            $mainProduct->setChildren(
                new ProductCollection(array_values($variants)),
            );
            $mainProducts->add($mainProduct);
        }

        return $mainProducts;
    }

    private function handleFirstActiveVariant(ProductEntity $product): ?ProductCollection
    {
        $mainProduct = null;
        $variants = new ProductCollection([$product]);

        foreach ($product->getChildren() as $child) {
            if ($child->getActive() && !$mainProduct) {
                $mainProduct = $child;
            } else {
                $variants->add($child);
            }
        }

        if (!$mainProduct) {
            return null;
        }

        $mainProduct->setChildren($variants);

        return new ProductCollection([$mainProduct]);
    }

    private function handleProduct(
        SalesChannelProductEntity $product,
        SalesChannelContext $context,
        Account $account,
        bool $hideProductsAfterClearance,
        array $mapping,
    ): ?NostoProduct {
        $stock = $this->configProvider->getStockField(
            $context->getSalesChannelId(),
            $context->getLanguageId(),
        ) === StockFieldOptions::ACTUAL_STOCK
            ? $product->getStock()
            : $product->getAvailableStock();

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

    private function deleteVariantProducts(
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

    private function validateProduct(string $productNumber, NostoProduct $product): ?Job\JobRuntimeMessageInterface
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

    private function doDeleteOperation(
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

    private function getIdentifiers(SalesChannelContext $context, array $productIds, array $mapping): array
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

    private function getProductNumbers(array $productIds, array $mapping): array
    {
        $productNumbers = [];

        foreach ($productIds as $productId) {
            if (!empty($mapping[$productId])) {
                $productNumbers[] = $mapping[$productId];
            }
        }

        return $productNumbers;
    }

    private function getDomainUrl(
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

    protected function getShopwareProducts(array $productIds, SalesChannelContext $context): SalesChannelProductCollection
    {
        $criteria = new Criteria($productIds);

        return $this->salesChannelProductRepository->search(
            $criteria,
            $context,
        )->getEntities();
    }
}
