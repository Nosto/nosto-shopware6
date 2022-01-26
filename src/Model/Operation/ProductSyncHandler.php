<?php

namespace Od\NostoIntegration\Model\Operation;

use Nosto\Operation\UpsertProduct;
use Od\NostoIntegration\Async\ProductSyncMessage;
use Od\NostoIntegration\Model\Nosto\Account;
use Od\NostoIntegration\Model\Nosto\Entity\Product\ProductProviderInterface;
use Od\Scheduler\Model\Job;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductSyncHandler implements Job\JobHandlerInterface
{
    public const HANDLER_CODE = 'od-nosto-product-sync';

    private SalesChannelRepositoryInterface $productRepository;
    private SalesChannelContextFactory $channelContextFactory;
    private ProductProviderInterface $productProvider;
    private Account\Provider $accountProvider;

    public function __construct(
        SalesChannelRepositoryInterface $productRepository,
        SalesChannelContextFactory $channelContextFactory,
        ProductProviderInterface $productProvider,
        Account\Provider $accountProvider
    ) {
       $this->productRepository = $productRepository;
       $this->channelContextFactory = $channelContextFactory;
       $this->productProvider = $productProvider;
       $this->accountProvider = $accountProvider;
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
            foreach ($accountOperationResult->getErrors() as $error) {
                $operationResult->addError($error);
            }
        }

        return $operationResult;
    }

    private function doOperation(Account $account, SalesChannelContext $context, array $productIds): Job\JobResult
    {
        $operationResult = new Job\JobResult();
        $operation = new UpsertProduct($account->getNostoAccount()); //TODO add active domain argument
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $productIds));
        $products = $this->productRepository->search($criteria, $context);

        // TODO: check for deleted products

        /** @var ProductEntity $product */
        foreach ($products as $product) {
            try {
                // TODO: up to 2MB payload !
                $nostoProduct = $this->productProvider->get($product, $context);
                $operation->addProduct($nostoProduct);
            } catch (\Throwable $e) {
                $operationResult->addError(new \Exception(
                    \sprintf('Sync Product[id: %s] error: %s', $product->getId(), $e->getMessage()))
                );
                continue;
            }
        }

        try {
            $operation->upsert();
        } catch (\Exception $e) {
            $operationResult->addError($e);
        }

        return $operationResult;
    }
}
