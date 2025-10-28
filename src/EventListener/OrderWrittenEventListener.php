<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\EventListener;

use Nosto\NostoIntegration\Async\EventsWriter;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class OrderWrittenEventListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly EventsWriter $eventsWriter,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
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
        $cookieValue = $this->requestStack->getCurrentRequest()?->cookies->get('2c_cId');
        if (!$cookieValue) {
            $this->logger->error(
                'Skipping ORDER_ENTITY_PLACED event because 2c_cId cookie is missing',
                [
                    'orderId' => $event->getOrder()->getId(),
                    'orderNumber' => $event->getOrder()->getOrderNumber(),
                ],
            );
            return;
        }
        $this->eventsWriter->writeEvent(
            $this->eventsWriter::ORDER_ENTITY_PLACED_NAME,
            $event->getOrder()->getId(),
            $event->getContext(),
            $cookieValue,
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
