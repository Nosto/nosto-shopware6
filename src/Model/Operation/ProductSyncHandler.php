<?php

namespace Od\NostoIntegration\Model\Operation;

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
use Shopware\Core\Checkout\Cart\AbstractRuleLoader;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Uuid\Uuid;
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

        foreach ($this->accountProvider->all() as $account) {
            $channelContext = $this->channelContextFactory->create(
                Uuid::randomHex(),
                $account->getChannelId(),
                [SalesChannelContextService::LANGUAGE_ID => $account->getLanguageId()]
            );
            $channelContext->setRuleIds($this->loadRuleIds($channelContext));

            $accountOperationResult = $this->doOperation($account, $channelContext, $message->getProductIds());
            foreach ($accountOperationResult->getErrors() as $error) {
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
                $this->doUpsertOperation($account, $context, $products->getEntities());
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
        ProductCollection $productCollection
    ) {
        $domainUrl = $context->getSalesChannel()->getDomains()->first()->getUrl();
        $domain = parse_url($domainUrl, PHP_URL_HOST);
        $operation = new UpsertProduct($account->getNostoAccount(), $domain);

        /** @var SalesChannelProductEntity $product */
        foreach ($productCollection as $product) {
            // TODO: up to 2MB payload !
            $nostoProduct = $this->productProvider->get($product, $context);
            $operation->addProduct($nostoProduct);
        }
        $this->eventDispatcher->dispatch(new BeforeUpsertProductsEvent($operation, $context->getContext()));
        $operation->upsert();
    }

    private function doDeleteOperation(Account $account, SalesChannelContext $context, array $productIds)
    {
        $domainUrl = $context->getSalesChannel()->getDomains()->first()->getUrl();
        $domain = parse_url($domainUrl, PHP_URL_HOST);

        $operation = new DeleteProduct($account->getNostoAccount(), $domain);
        $operation->setProductIds($productIds);
        $this->eventDispatcher->dispatch(new BeforeDeleteProductsEvent($operation, $context->getContext()));
        $operation->delete();
    }
}
