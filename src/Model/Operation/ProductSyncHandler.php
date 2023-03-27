<?php

namespace Od\NostoIntegration\Model\Operation;

use Nosto\Model\Product\Product as NostoProduct;
use Nosto\Operation\DeleteProduct;
use Nosto\Operation\UpsertProduct;
use Od\NostoIntegration\Async\ProductSyncMessage;
use Od\NostoIntegration\Model\ConfigProvider;
use Od\NostoIntegration\Model\Nosto\Account;
use Od\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Od\NostoIntegration\Model\Nosto\Entity\Product\ProductProviderInterface;
use Od\NostoIntegration\Model\Operation\Event\BeforeDeleteProductsEvent;
use Od\NostoIntegration\Model\Operation\Event\BeforeUpsertProductsEvent;
use Od\Scheduler\Model\Job;
use Od\Scheduler\Model\Job\Message\WarningMessage;
use Shopware\Core\Checkout\Cart\AbstractRuleLoader;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ProductSyncHandler implements Job\JobHandlerInterface
{
    public const HANDLER_CODE = 'od-nosto-product-sync';

    private SalesChannelRepositoryInterface $productRepository;
    private AbstractSalesChannelContextFactory $channelContextFactory;
    private ProductProviderInterface $productProvider;
    private Account\Provider $accountProvider;
    private ConfigProvider $configProvider;
    private AbstractRuleLoader $ruleLoader;
    private ProductHelper $productHelper;
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        SalesChannelRepositoryInterface $productRepository,
        AbstractSalesChannelContextFactory $channelContextFactory,
        ProductProviderInterface $productProvider,
        Account\Provider $accountProvider,
        ConfigProvider $configProvider,
        AbstractRuleLoader $ruleLoader,
        ProductHelper $productHelper,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->productRepository = $productRepository;
        $this->channelContextFactory = $channelContextFactory;
        $this->productProvider = $productProvider;
        $this->accountProvider = $accountProvider;
        $this->configProvider = $configProvider;
        $this->ruleLoader = $ruleLoader;
        $this->productHelper = $productHelper;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param ProductSyncMessage $message
     *
     * @return Job\JobResult
     */
    public function execute(object $message): Job\JobResult
    {
        $operationResult = new Job\JobResult();

        foreach ($this->accountProvider->all($message->getContext()) as $account) {
            $channelContext = $this->channelContextFactory->create(
                Uuid::randomHex(),
                $account->getChannelId(),
                [SalesChannelContextService::LANGUAGE_ID => $account->getLanguageId()]
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
            }
        )->getIds();
    }

    private function doOperation(Account $account, SalesChannelContext $context, array $productIds): Job\JobResult
    {
        $result = new Job\JobResult();
        $existentProductsCollection = $this->productHelper->loadProducts($productIds, $context);
        $deletedProductIds = array_diff($productIds, $existentProductsCollection->getIds());
        $existentParentProductIds = \array_map(function (ProductEntity $product) {
            return $product->getParentId() === null ? $product->getId() : $product->getParentId();
        }, $existentProductsCollection->getElements());

        $products = $this->productHelper->loadExistingParentProducts($existentParentProductIds, $context);

        try {
            if ($products->count() !== 0) {
                $this->doUpsertOperation($account, $context, $products->getEntities(), $result);
            }

            if (!empty($deletedProductIds)) {
                $this->doDeleteOperation($account, $context, $deletedProductIds);
            }
        } catch (\Throwable $e) {
            $result->addError($e);
        }

        return $result;
    }

    private function doUpsertOperation(
        Account $account,
        SalesChannelContext $context,
        ProductCollection $productCollection,
        Job\JobResult $result
    ) {
        $domainUrl = $this->getDomainUrl($context->getSalesChannel()->getDomains(), $context->getSalesChannelId());
        $domain = parse_url($domainUrl, PHP_URL_HOST);
        $operation = new UpsertProduct($account->getNostoAccount(), $domain);

        /** @var SalesChannelProductEntity $product */
        foreach ($productCollection as $product) {
            // TODO: up to 2MB payload !
            $nostoProduct = $this->productProvider->get($product, $context);
            $invalidMessage = $this->validateProduct($product->getProductNumber(), $nostoProduct);
            if ($invalidMessage) {
                $result->addMessage($invalidMessage);
                continue;
            }
            $operation->addProduct($nostoProduct);
        }
        $this->eventDispatcher->dispatch(new BeforeUpsertProductsEvent($operation, $context->getContext()));
        $operation->upsert();
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
            $message.'ignoring upsert for product with number. '.$productNumber
        );
    }

    private function doDeleteOperation(Account $account, SalesChannelContext $context, array $productIds, ProductCollection $productCollection)
    {
        $identifiers = $this->getIdentifiers($context, $productIds, $productCollection);
        $domainUrl = $this->getDomainUrl($context->getSalesChannel()->getDomains(), $context->getSalesChannelId());
        $domain = parse_url($domainUrl, PHP_URL_HOST);

        $operation = new DeleteProduct($account->getNostoAccount(), $domain);
        $operation->setProductIds($identifiers);
        $this->eventDispatcher->dispatch(new BeforeDeleteProductsEvent($operation, $context->getContext()));
        $operation->delete();
    }

    private function getIdentifiers(SalesChannelContext $context, array $productIds, ProductCollection $productCollection): array
    {
        $identifierType = $this->configProvider->getProductIdentifier($context->getSalesChannelId());
        if ($identifierType === 'product-number') {
            return $this->getProductNumbers($productIds, $productCollection);
        }
        return $productIds;
    }

    private function getProductNumbers(array $productIds, ProductCollection $productCollection): array
    {
        $productNumbers = [];

        foreach ($productIds as $productId) {
            if ($productCollection->get($productId)) {
                $productNumbers[] = $productCollection->get($productId)->getProductNumber();
            }
        }

        return $productNumbers;
    }

    private function getDomainUrl(?SalesChannelDomainCollection $domains, ?string $channelId): string {
        if($domains == null || $domains->count() < 1) {
            return '';
        }
        $domainId = (string) $this->configProvider->getDomainId($channelId);
        return $domains->has($domainId) ? $domains->get($domainId)->getUrl() : $domains->first()->getUrl();
    }
}
