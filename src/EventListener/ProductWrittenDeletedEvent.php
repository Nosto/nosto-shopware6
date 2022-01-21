<?php

namespace Od\NostoIntegration\EventListener;

use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductWrittenDeletedEvent implements EventSubscriberInterface
{

    public static function getSubscribedEvents()
    {
        return [
            ProductEvents::PRODUCT_WRITTEN_EVENT => 'onProductChange',
            ProductEvents::PRODUCT_DELETED_EVENT => 'onProductChange'
        ];
    }

    public function onProductChange(EntityWrittenEvent $entityWrittenEvent,EntityDeletedEvent $entityDeletedEvent)
    {
        $test = 'test';
    }
}