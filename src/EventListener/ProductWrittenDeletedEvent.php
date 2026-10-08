<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\EventListener;

use Nosto\NostoIntegration\Async\EventsWriter;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Account\Provider as AccountProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ProductWrittenDeletedEvent implements EventSubscriberInterface
{
    public function __construct(
        private readonly EventsWriter $eventsWriter,
        private readonly ProductHelper $productHelper,
        private readonly ConfigProvider $configProvider,
        private readonly AccountProvider $accountProvider,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_WRITTEN_EVENT => 'onProductWritten',
            EntityDeleteEvent::class => 'beforeDelete',
            KernelEvents::RESPONSE => 'onResponse',
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $isEnabledCache = false;
        $isNavigationEnabled = false;
        $context = null;

        if ($request->attributes->has('sw-sales-channel-context')) {
            /** @var SalesChannelContext $salesChannelContext */
            $context = $request->attributes->get('sw-sales-channel-context');
            $isEnabledCache = $this->configProvider->isCacheEnabled(
                $context->getSalesChannelId(),
                $context->getLanguageId(),
            );
            $isNavigationEnabled = $this->configProvider->isNavigationEnabled(
                $context->getSalesChannelId(),
                $context->getLanguageId(),
            );
        }

        if ((str_starts_with($request->getPathInfo(), '/navigation/') || $request->attributes->get(
            '_route',
        ) === 'frontend.navigation.page') && $isNavigationEnabled) {
            $response = $event->getResponse();

            if ($isEnabledCache) {
                $cacheTtl = $this->configProvider->getCacheTtl(
                    $context->getSalesChannelId(),
                    $context->getLanguageId(),
                );
                $cacheTtlSeconds = $cacheTtl * 60;
                $response->setPublic();
                $response->setMaxAge($cacheTtlSeconds);
                $response->setSharedMaxAge($cacheTtlSeconds);
                $response->setStaleWhileRevalidate($cacheTtlSeconds);
                // Category results can vary by Nosto's visitor and experiment cookies. Ensure
                // that shared HTTP caches keep a separate representation for each cookie header.
                $response->setVary(['Cookie'], false);
                $expiryTime = new \DateTimeImmutable(sprintf('+%d seconds', $cacheTtlSeconds));
                $response->setExpires($expiryTime);
            } else {
                $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
                $response->headers->set('Pragma', 'no-cache');
            }
        }
    }

    public function onProductWritten(EntityWrittenEvent $event): void
    {
        $ids = $this->includeParentIds(
            $event->getIds(),
            $event->getContext(),
            $this->getPreviousParentIds($event),
        );
        $orderNumberMapping = $this->productHelper->loadOrderNumberMapping($ids, $event->getContext());

        $this->writeEvents($ids, $event->getEntityName(), $event->getContext(), $orderNumberMapping);
    }

    public function beforeDelete(EntityDeleteEvent $event): void
    {
        $ids = $event->getIds(ProductDefinition::ENTITY_NAME);

        if (count($ids)) {
            $ids = $this->includeParentIds($ids, $event->getContext());
            $orderNumberMapping = $this->productHelper->loadOrderNumberMapping($ids, $event->getContext());

            $event->addSuccess(function () use ($ids, $event, $orderNumberMapping): void {
                $this->writeEvents($ids, ProductDefinition::ENTITY_NAME, $event->getContext(), $orderNumberMapping);
            });
        }
    }

    /**
     * @param array<string> $ids
     * @return array<string>
     */
    private function includeParentIds(array $ids, Context $context, array $additionalParentIds = []): array
    {
        if (!$this->isParentStockDerivationEnabledForAnyAccount($context)) {
            return $ids;
        }

        // Variant changes must resync their current parent; reassignment also supplies the previous parent here.
        $parentIds = $this->productHelper->loadParentIdMapping($ids, $context);

        return array_values(array_unique([...$ids, ...array_values($parentIds), ...$additionalParentIds]));
    }

    /**
     * @return array<string>
     */
    private function getPreviousParentIds(EntityWrittenEvent $event): array
    {
        $parentIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            if (!$writeResult->hasPayload('parentId')) {
                continue;
            }

            // The existence state contains the database values from before the write was applied.
            $parentId = $writeResult->getExistence()?->getState()['parent_id'] ?? null;
            if (!is_string($parentId)) {
                continue;
            }

            if (!Uuid::isValid($parentId)) {
                $parentId = Uuid::fromBytesToHex($parentId);
            }

            $parentIds[] = $parentId;
        }

        return $parentIds;
    }

    private function isParentStockDerivationEnabledForAnyAccount(Context $context): bool
    {
        foreach ($this->accountProvider->all($context) as $account) {
            if ($this->configProvider->isEnabledDeriveParentStockFromVariants(
                $account->getChannelId(),
                $account->getLanguageId(),
            )) {
                return true;
            }
        }

        return false;
    }

    private function writeEvents(array $ids, string $entityName, Context $context, array $orderNumberMapping): void
    {
        foreach ($ids as $productId) {
            if (!empty($orderNumberMapping[$productId])) {
                $this->eventsWriter->writeEvent(
                    $entityName,
                    $productId,
                    $context,
                    $orderNumberMapping[$productId],
                );
            }
        }
    }
}
