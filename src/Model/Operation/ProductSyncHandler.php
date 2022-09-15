<?php

namespace Od\NostoIntegration\Model\Operation;

use Nosto\Model\Product\Product as NostoProduct;
use Nosto\Operation\DeleteProduct;
use Nosto\Operation\UpsertProduct;
use Od\NostoIntegration\Async\ProductSyncMessage;
use Od\NostoIntegration\Model\ConfigProvider;
use Od\NostoIntegration\Model\Nosto\Account;
use Od\NostoIntegration\Model\Nosto\Entity\Product\ProductProviderInterface;
use Od\Scheduler\Model\Job;
use Od\Scheduler\Model\Job\Message\WarningMessage;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\{EqualsAnyFilter, EqualsFilter};
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductSyncHandler implements Job\JobHandlerInterface
{
    public const HANDLER_CODE = 'od-nosto-product-sync';

    private SalesChannelRepositoryInterface $productRepository;
    private AbstractSalesChannelContextFactory $channelContextFactory;
    private ProductProviderInterface $productProvider;
    private Account\Provider $accountProvider;
    private ConfigProvider $configProvider;

    public function __construct(
        SalesChannelRepositoryInterface $productRepository,
        AbstractSalesChannelContextFactory $channelContextFactory,
        ProductProviderInterface $productProvider,
        Account\Provider $accountProvider,
        ConfigProvider $configProvider
    ) {
        $this->productRepository = $productRepository;
        $this->channelContextFactory = $channelContextFactory;
        $this->productProvider = $productProvider;
        $this->accountProvider = $accountProvider;
        $this->configProvider = $configProvider;
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

            $accountOperationResult = $this->doOperation($account, $channelContext, $message->getProductIds());
            foreach ($accountOperationResult->getMessages() as $error) {
                $operationResult->addMessage($error);
            }
        }

        return $operationResult;
    }

    private function doOperation(Account $account, SalesChannelContext $context, array $productIds): Job\JobResult
    {
        $result = new Job\JobResult();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $productIds));

        if (!$this->configProvider->isEnabledSyncInactiveProducts($context->getSalesChannelId())) {
            $criteria->addFilter(new EqualsFilter('active', true));
        }

        $existentProductsCollection = $this->productRepository->search($criteria, $context)->getEntities();
        $deletedProductIds = array_diff($productIds, $existentProductsCollection->getIds());
        $existentParentProductIds = \array_map(function (ProductEntity $product) {
            return $product->getParentId() === null ? $product->getId() : $product->getParentId();
        }, $existentProductsCollection->getElements());

        $criteria = new Criteria();
        $criteria->addAssociation('media');
        $criteria->addAssociation('options.group');
        $criteria->addAssociation('properties.group');
        $criteria->addAssociation('children.media');
        $criteria->addAssociation('children.options.group');
        $criteria->addAssociation('children.properties.group');
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('children.manufacturer');
        $criteria->addAssociation('categoriesRo');
        $criteria->addAssociation('children.categoriesRo');

        if (!$this->configProvider->isEnabledSyncInactiveProducts($context->getSalesChannelId())) {
            $criteria->addFilter(new EqualsFilter('active', true));
        }

        $criteria->addFilter(new EqualsAnyFilter('id', array_unique(array_values($existentParentProductIds))));
        $products = $this->productRepository->search($criteria, $context);

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
        $domainUrl = $context->getSalesChannel()->getDomains()->first()->getUrl();
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

        $operation->upsert();
    }

    private function validateProduct(string $productNumber, NostoProduct $product): ?Job\JobRuntimeMessageInterface
    {
        if (!$product->getImageUrl()) {
            return new WarningMessage('Image url is empty, ignoring upsert for product with number. ' . $productNumber);
        }
        return null;
    }

    private function doDeleteOperation(Account $account, SalesChannelContext $context, array $productIds)
    {
        $domainUrl = $context->getSalesChannel()->getDomains()->first()->getUrl();
        $domain = parse_url($domainUrl, PHP_URL_HOST);

        $operation = new DeleteProduct($account->getNostoAccount(), $domain);
        $operation->setProductIds($productIds);
        $operation->delete();
    }
}
