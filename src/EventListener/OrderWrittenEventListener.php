<?php

namespace Od\NostoIntegration\EventListener;

use Od\NostoIntegration\Async\EventsWriter;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderWrittenEventListener implements EventSubscriberInterface
{
    private EventsWriter $eventsWriter;

    private const ORDER_ENTITY_NAME = 'order';

    public function __construct(EventsWriter $eventsWriter)
    {
        $this->eventsWriter = $eventsWriter;
    }

    public static function getSubscribedEvents()
    {
        return [
            'state_machine.order.state_changed' => 'onOrderWritten',
            CheckoutOrderPlacedEvent::class     => 'onCheckoutOrderPlaced',

        ];
    }

    public function onCheckoutOrderPlaced(CheckoutOrderPlacedEvent $event)
    {
        $this->eventsWriter->writeEvent(
            self::ORDER_ENTITY_NAME,
            $event->getOrderId(),
            $event->getContext());
    }

    public function onOrderWritten(StateMachineStateChangeEvent $event)
    {
        $this->eventsWriter->writeEvent(
            self::ORDER_ENTITY_NAME,
            $event->getStateMachine()->getId(),
            $event->getContext());
    }
}