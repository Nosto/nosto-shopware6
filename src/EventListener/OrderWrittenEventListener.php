<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\EventListener;

use Nosto\NostoIntegration\Async\EventsWriter;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderWrittenEventListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly EventsWriter $eventsWriter,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'state_machine.order.state_changed' => 'onOrderWritten',
            CheckoutOrderPlacedEvent::class => 'onCheckoutOrderPlaced',
        ];
    }

    public function onCheckoutOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $this->eventsWriter->writeEvent(
            $this->eventsWriter::ORDER_ENTITY_PLACED_NAME,
            $event->getOrder()->getId(),
            $event->getContext(),
        );
    }

    public function onOrderWritten(StateMachineStateChangeEvent $event): void
    {
        $this->eventsWriter->writeEvent(
            $this->eventsWriter::ORDER_ENTITY_UPDATED_NAME,
            $event->getTransition()->getEntityId(),
            $event->getContext(),
        );
    }
}
