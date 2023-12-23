<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\EventListener;

use Nosto\NostoIntegration\Async\EventsWriter;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductWrittenDeletedEvent implements EventSubscriberInterface
{
    public function __construct(
        private readonly EventsWriter $eventsWriter,
        private readonly ProductHelper $productHelper,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_WRITTEN_EVENT => 'onProductWritten',
        ];
    }

    public function onProductWritten(EntityWrittenEvent $event): void
    {
        $orderNumberMapping = $this->productHelper->loadOrderNumberMapping($event->getIds(), $event->getContext());

        foreach ($event->getIds() as $productId) {
            if (!empty($orderNumberMapping[$productId])) {
                $this->eventsWriter->writeEvent(
                    $event->getEntityName(),
                    $productId,
                    $event->getContext(),
                    $orderNumberMapping[$productId],
                );
            }
        }
    }
}
