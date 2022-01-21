<?php

namespace Od\NostoIntegration\EventListener;

use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderStateChangedEventListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            'state_machine.order.state_changed'             => 'onStateChange',
            'state_machine.order_transaction.state_changed' => 'onStateChange',
            'order.written' => 'onStateChange',
        ];
    }

    public function onStateChange(StateMachineStateChangeEvent $stateChangeEvent,EntityWrittenEvent $entityWrittenEvent)
    {
//        $entity = $event->getEntityName();
//        $entityId = $event->getIds();
        $test = 'test';
    }
}