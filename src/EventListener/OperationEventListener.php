<?php

namespace Od\NostoIntegration\EventListener;

use Od\NostoIntegration\Model\Operation\Event\BeforeDeleteProductsEvent;
use Od\NostoIntegration\Model\Operation\Event\BeforeMarketingOperationEvent;
use Od\NostoIntegration\Model\Operation\Event\BeforeOperationEvent;
use Od\NostoIntegration\Model\Operation\Event\BeforeOrderCreatedEvent;
use Od\NostoIntegration\Model\Operation\Event\BeforeUpsertProductsEvent;
use Od\NostoIntegration\Utils\RequestHelper\RequestHelperInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OperationEventListener implements EventSubscriberInterface
{
    private RequestHelperInterface $requestHelper;

    public function __construct(RequestHelperInterface $requestHelper)
    {
        $this->requestHelper = $requestHelper;
    }

    public static function getSubscribedEvents()
    {
        return [
            BeforeUpsertProductsEvent::class => 'beforeOperation',
            BeforeOrderCreatedEvent::class => 'beforeOperation',
            BeforeMarketingOperationEvent::class => 'beforeOperation',
            BeforeDeleteProductsEvent::class => 'beforeOperation',
        ];
    }

    public function beforeOperation(BeforeOperationEvent $event)
    {
        $this->requestHelper->initUserAgent($event->getContext());
    }
}
