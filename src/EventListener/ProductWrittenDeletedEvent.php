<?php

namespace Od\NostoIntegration\EventListener;

use Od\NostoIntegration\Async\EventsWriter;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductWrittenDeletedEvent implements EventSubscriberInterface
{
    private EventsWriter $eventsWriter;

    public function __construct(EventsWriter $eventsWriter)
    {
        $this->eventsWriter = $eventsWriter;
    }

    public static function getSubscribedEvents()
    {
        return [
            ProductEvents::PRODUCT_WRITTEN_EVENT => 'onProductWritten',
            ProductEvents::PRODUCT_DELETED_EVENT => 'onProductDeleted'
        ];
    }

    public function onProductWritten(EntityWrittenEvent $event)
    {
        foreach ($event->getIds() as $productId) {
            $this->eventsWriter->writeEvent(
                $event->getEntityName(),
                $productId,
                $event->getContext()
            );
        }
    }

    public function onProductDeleted(EntityDeletedEvent $event)
    {
        foreach ($event->getIds() as $productId) {
            $this->eventsWriter->writeEvent(
                $event->getEntityName(),
                $productId,
                $event->getContext()
            );
        }
    }
}
