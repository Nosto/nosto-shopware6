<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service;

use Nosto\NostoException;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Account;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\ProductProviderInterface;
use Nosto\NostoIntegration\Model\Operation\ProductSyncHandler;
use Nosto\NostoIntegration\Utils\ProductTaggingHelper;
use Nosto\Request\Http\Exception\AbstractHttpException;
use Nosto\Scheduler\Model\Job;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\AbstractRuleLoader;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class NostoMonitoringProductDebug extends ProductSyncHandler
{
    private array $nostoProducts = [];

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
        parent::__construct(
            $this->channelContextFactory,
            $this->productProvider,
            $this->accountProvider,
            $this->configProvider,
            $this->ruleLoader,
            $this->productHelper,
            $this->eventDispatcher,
            $this->systemConfigService,
            $this->logger,
        );
    }

    public function execute(object $message): Job\JobResult
    {
        foreach ($this->accountProvider->all($message->getContext()) as $account) {
            $channelContext = $this->channelContextFactory->create(
                Uuid::randomHex(),
                $account->getChannelId(),
                [
                    SalesChannelContextService::LANGUAGE_ID => $account->getLanguageId(),
                ],
            );

            $channelContext->setRuleIds($this->loadRuleIds($channelContext));

            $this->doOperation($account, $channelContext, $message->getProductIds());
        }

        return new Job\JobResult($this->nostoProducts);
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

            $handledProducts = $productTaggingHelper->findProductId(
                $context,
                $product,
                null,
                true,
                true,
            );
            $shopwareProducts = $handledProducts->count()
                ? $this->productHelper->getShopwareProducts($handledProducts->getIds(), $context)
                : new ProductCollection();

            foreach ($handledProducts as $handledProduct) {
                $shopwareProduct = $shopwareProducts->get($handledProduct->getId());

                if ($shopwareProduct) {
                    $shopwareProduct->setChildren($handledProduct->getChildren());
                    if ($nostoProduct = $this->handleProduct(
                        $shopwareProduct,
                        $context,
                        $account,
                        $hideProductsAfterClearance,
                        $ids,
                    )
                    ) {
                        $nostoProducts[] = $nostoProduct;
                    }
                } else {
                    $this->deleteVariantProducts($handledProduct, $context, $account, $ids);
                    $this->doDeleteOperation(
                        $account,
                        $context,
                        [$handledProduct->getId(), $handledProduct->getParentId()],
                        $ids,
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
                }

                $this->nostoProducts[] = $preparedProductForSync;
            }
        }
    }

    protected function doDeleteOperation(
        Account $account,
        SalesChannelContext $context,
        array $productIds,
        array $mapping,
    ): void {
    }
}
