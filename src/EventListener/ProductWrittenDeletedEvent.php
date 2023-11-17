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
    private EventsWriter $eventsWriter;

    private ProductHelper $productHelper;

    public function __construct(EventsWriter $eventsWriter, ProductHelper $productHelper)
    {
        $this->eventsWriter = $eventsWriter;
        $this->productHelper = $productHelper;
    }

    public static function getSubscribedEvents()
    {
        return [
            ProductEvents::PRODUCT_WRITTEN_EVENT => 'onProductWritten',
        ];
    }

    public function onProductWritten(EntityWrittenEvent $event)
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
