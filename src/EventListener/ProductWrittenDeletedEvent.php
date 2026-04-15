<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\EventListener;

use Nosto\NostoIntegration\Async\EventsWriter;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
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
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_WRITTEN_EVENT => 'onProductWritten',
            BeforeDeleteEvent::class => 'beforeDelete',
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
        $orderNumberMapping = $this->productHelper->loadOrderNumberMapping($event->getIds(), $event->getContext());

        $this->writeEvents($event->getIds(), $event->getEntityName(), $event->getContext(), $orderNumberMapping);
    }

    public function beforeDelete(BeforeDeleteEvent $event): void
    {
        $ids = $event->getIds(ProductDefinition::ENTITY_NAME);

        if (count($ids)) {
            $orderNumberMapping = $this->productHelper->loadOrderNumberMapping($ids, $event->getContext());

            $event->addSuccess(function () use ($ids, $event, $orderNumberMapping) {
                $this->writeEvents($ids, ProductDefinition::ENTITY_NAME, $event->getContext(), $orderNumberMapping);
            });
        }
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
