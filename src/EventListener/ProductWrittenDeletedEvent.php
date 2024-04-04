<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\EventListener;

use Nosto\NostoIntegration\Async\EventsWriter;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
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
            EntityDeleteEvent::class => 'beforeDelete',
        ];
    }

    public function onProductWritten(EntityWrittenEvent $event): void
    {
        $orderNumberMapping = $this->productHelper->loadOrderNumberMapping($event->getIds(), $event->getContext());

        $this->writeEvents($event->getIds(), $event->getEntityName(), $event->getContext(), $orderNumberMapping);
    }

    public function beforeDelete(EntityDeleteEvent $event): void
    {
        $ids = $event->getIds(ProductDefinition::ENTITY_NAME);

        if (count($ids)) {
            $orderNumberMapping = $this->productHelper->loadOrderNumberMapping($ids, $event->getContext());

            $event->addSuccess(function () use ($ids, $event, $orderNumberMapping): void {
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
