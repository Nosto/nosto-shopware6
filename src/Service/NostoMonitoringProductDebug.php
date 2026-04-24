<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service;

use Nosto\NostoException;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Account;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProduct;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProductCollection;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProductConverter;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProvider;
use Nosto\NostoIntegration\Model\Operation\ProductSyncHandler;
use Nosto\NostoIntegration\Utils\ProductTaggingHelper;
use Nosto\Request\Http\Exception\AbstractHttpException;
use Nosto\Scheduler\Model\Job;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\AbstractRuleLoader;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
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
        private readonly PartialProvider $partialProductProvider,
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
            $this->partialProductProvider,
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
        PartialProductCollection $productCollection,
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
            $this->partialProductProvider,
            $this->productHelper,
        );

        /** @var PartialProduct $product */
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
            if ($handledProducts instanceof EntityCollection && !$handledProducts instanceof PartialProductCollection) {
                $handledProducts = PartialProductConverter::toPartialProductCollection($handledProducts);
            }
            $shopwareProducts = $handledProducts->count()
                ? $this->productHelper->getShopwareProductsPartial($handledProducts->getIds(), $context)
                : new PartialProductCollection();

            foreach ($handledProducts as $handledProduct) {
                if ($handledProduct instanceof PartialEntity && !$handledProduct instanceof PartialProduct) {
                    $handledProduct = PartialProductConverter::toPartialProduct($handledProduct);
                }

                $shopwareProduct = $shopwareProducts->get($handledProduct->getId());
                if ($shopwareProduct instanceof PartialEntity && !$shopwareProduct instanceof PartialProduct) {
                    $shopwareProduct = PartialProductConverter::toPartialProduct($shopwareProduct);
                }

                if ($shopwareProduct) {
                    $shopwareProduct->setChildren($handledProduct->getChildren());
                    if ($nostoProduct = $this->handleProduct(
                        $shopwareProduct,
                        $context,
//                        $account,
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
