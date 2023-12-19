<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Operation;

use Nosto\Model\Product\Product as NostoProduct;
use Nosto\NostoException;
use Nosto\NostoIntegration\Async\ProductSyncMessage;
use Nosto\NostoIntegration\Decorator\Core\Content\Product\DataAbstractionLayer\VariantListingConfig;
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
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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

    public const PRODUCT_ASSIGNMENT_TYPE = 'productAssignmentType';

    private AbstractSalesChannelContextFactory $channelContextFactory;

    private ProductProviderInterface $productProvider;

    private Account\Provider $accountProvider;

    private ConfigProvider $configProvider;

    private AbstractRuleLoader $ruleLoader;

    private ProductHelper $productHelper;

    private EventDispatcherInterface $eventDispatcher;

    private SalesChannelRepository $categoryRepository;

    private SystemConfigService $systemConfigService;

    public function __construct(
        AbstractSalesChannelContextFactory $channelContextFactory,
        ProductProviderInterface $productProvider,
        Account\Provider $accountProvider,
        ConfigProvider $configProvider,
        AbstractRuleLoader $ruleLoader,
        ProductHelper $productHelper,
        EventDispatcherInterface $eventDispatcher,
        SalesChannelRepository $categoryRepository,
        SystemConfigService $systemConfigService,
    ) {
        $this->channelContextFactory = $channelContextFactory;
        $this->productProvider = $productProvider;
        $this->accountProvider = $accountProvider;
        $this->configProvider = $configProvider;
        $this->ruleLoader = $ruleLoader;
        $this->productHelper = $productHelper;
        $this->eventDispatcher = $eventDispatcher;
        $this->categoryRepository = $categoryRepository;
        $this->systemConfigService = $systemConfigService;
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
        $existentProductsCollection = $this->productHelper->loadProducts($productIds, $context);
        $deletedProductIds = array_diff($productIds, $existentProductsCollection->getIds());
        $existentParentProductIds = array_map(function (ProductEntity $product) {
            return $product->getParentId() === null ? $product->getId() : $product->getParentId();
        }, $existentProductsCollection->getElements());

        $products = $this->productHelper->loadExistingParentProducts($existentParentProductIds, $context);

        try {
            if ($products->count() !== 0) {
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
        $dynamicGroupCategoryIds = $this->getCategoryIdsByDynamicGroups($context);

        $hideProductsAfterClearance = $this->systemConfigService->getBool(
            'core.listing.hideCloseoutProductsWhenOutOfStock',
            $channelId,
        );

        /** @var SalesChannelProductEntity $product */
        foreach ($productCollection as $product) {
            // Preparing categories for dynamic group products
            if (!empty($dynamicGroupCategoryIds) && $product->getStreamIds()) {
                $allProductCategoryPaths = '';
                $productCategoriesRo = $product->getCategoriesRo();

                foreach ($product->getStreamIds() as $streamId) {
                    if (array_key_exists($streamId, $dynamicGroupCategoryIds)) {
                        $allProductCategoryPaths .= $dynamicGroupCategoryIds[$streamId];
                    }
                }

                if ($productCategoriesRo && $productCategoriesRo->count()) {
                    foreach ($productCategoriesRo as $category) {
                        $allProductCategoryPaths .= '|' . $category->getId();
                    }
                }

                /** @var CategoryCollection $productCategoriesCollection */
                $productCategoriesCollection = $this->getCategoriesTreeCollection($allProductCategoryPaths, $context);

                if ($productCategoriesCollection->count() > 0) {
                    $product->setCategoriesRo($productCategoriesCollection);
                }
            }

            // TODO: up to 2MB payload!
            $nostoProducts = [];
            foreach ($this->processProductVariants($product, $context) as $handledProduct) {
                $nostoProducts[] = $this->handleProduct(
                    $handledProduct,
                    $context,
                    $account,
                    $hideProductsAfterClearance,
                    $ids,
                );
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

    private function processProductVariants(SalesChannelProductEntity $product, SalesChannelContext $context): array
    {
        $variantConfig = $product->getVariantListingConfig();
        $configuratorGroups = array_filter(
            $variantConfig?->getConfiguratorGroupConfig() ?? [],
            static fn (array $config) => $config['expressionForListings'],
        );

        if (!$product->getChildCount() || !($variantConfig instanceof VariantListingConfig)) {
            return [$product];
        }

        $mainProducts = [];
        if ($variantConfig->getDisplayCheapestVariant()) {
            $mainProducts[] = $this->handleCheapestVariant($product, $context);
        } elseif ($variantConfig->getMainVariantId()) {
            $mainProducts[] = $this->handleMainVariant($product, $variantConfig);
        } elseif (count($configuratorGroups)) {
            $mainProducts = array_merge(
                $mainProducts,
                $this->handleConfiguratorGroups($product),
            );
        }

        return count($mainProducts) ? $mainProducts : [$product];
    }

    private function handleCheapestVariant(
        SalesChannelProductEntity $product,
        SalesChannelContext $context,
    ): ProductEntity {
        $cheapestVariant = $product;
        $lowestPrice = null;

        foreach ($product->getChildren() as $child) {
            $variantPrice = $child->getCurrencyPrice($context->getCurrencyId())->getNet();

            if (is_null($lowestPrice) || $variantPrice < $lowestPrice) {
                $lowestPrice = $variantPrice;
                $cheapestVariant = $child;
            }
        }

        $cheapestVariant->setChildren(
            $product->getChildren()->filter(
                static fn (ProductEntity $child) => $child->getId() !== $cheapestVariant->getId(),
            ),
        );

        return $cheapestVariant;
    }

    private function handleMainVariant(
        SalesChannelProductEntity $product,
        VariantListingConfig $variantConfig,
    ): ProductEntity {
        $mainProduct = null;
        $variants = new ProductCollection([$product]);

        foreach ($product->getChildren() as $child) {
            if ($child->getId() === $variantConfig->getMainVariantId()) {
                $mainProduct = $child;
            } else {
                $variants->add($child);
            }
        }

        if (!$mainProduct) {
            return $product;
        }

        $mainProduct->setChildren($variants);

        return $mainProduct;
    }

    private function handleConfiguratorGroups(SalesChannelProductEntity $product): array
    {
        $groupedVariants = [];
        foreach ($product->getChildren() as $child) {
            $groupedVariants[$child->getDisplayGroup()][$child->getId()] = $child;
        }

        $mainProducts = [];
        foreach ($groupedVariants as $variants) {
            /** @var SalesChannelProductEntity $mainProduct */
            $mainProduct = array_shift($variants);
            $mainProduct->setChildren(
                new ProductCollection(array_values($variants)),
            );
            $mainProducts[] = $mainProduct;
        }

        return $mainProducts;
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
        ) === 'actual-stock'
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
        SalesChannelProductEntity $product,
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

    private function resolveVariantProducts(SalesChannelProductEntity $product, $context): array
    {
        $result = [];

        foreach ($product->getChildren()->getElements() as $variantOption) {
            $option = $this->productProvider->get($variantOption, $context);
            $option->setImageUrl($this->getVariantImage($variantOption));
            $result[] = $option;
        }

        return $result;
    }

    private function getVariantImage($variantOption)
    {
        foreach ($variantOption->getMedia()->getElements() as $mediaElement) {
            return $mediaElement->getMedia()->getUrl();
        }

        return null;
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
        if ($identifierType === 'product-number') {
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

    private function getCategoryIdsByDynamicGroups(SalesChannelContext $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter(self::PRODUCT_ASSIGNMENT_TYPE, CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM),
        );

        $categories = $this->categoryRepository->search($criteria, $context)->getEntities();
        $result = [];

        if ($categories->count() > 0) {
            foreach ($categories as $category) {
                if (!$category->getProductStreamId()) {
                    continue;
                }

                $result[$category->getProductStreamId()] = $category->getPath() . $category->getId();
            }
        }

        return $result;
    }

    private function getCategoriesTreeCollection($allProductCategoryPaths, $context): EntityCollection
    {
        $categoriesPaths = array_filter(array_unique(explode('|', $allProductCategoryPaths)));

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsAnyFilter('id', $categoriesPaths),
        );

        return $this->categoryRepository->search($criteria, $context)->getEntities();
    }
}
