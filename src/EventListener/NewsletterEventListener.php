<?php

namespace Od\NostoIntegration\EventListener;

use Od\NostoIntegration\Async\EventsWriter;
use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterUnsubscribeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NewsletterEventListener implements EventSubscriberInterface
{
    private EventsWriter $eventsWriter;

    public function __construct(EventsWriter $eventsWriter)
    {
        $this->eventsWriter = $eventsWriter;
    }

    public static function getSubscribedEvents()
    {
        return [
            NewsletterConfirmEvent::class     => 'onNewsletterConfirm',
            NewsletterUnsubscribeEvent::class => 'onNewsletterUnsubscribe',
        ];
    }

    public function onNewsletterConfirm(NewsletterConfirmEvent $event)
    {
        $this->eventsWriter->writeEvent(
            $this->eventsWriter::NEWSLETTER_ENTITY_NAME,
            $event->getNewsletterRecipient()->getId(),
            $event->getContext()
        );
    }

    public function onNewsletterUnsubscribe(NewsletterUnsubscribeEvent $event)
    {
        $this->eventsWriter->writeEvent(
            $this->eventsWriter::NEWSLETTER_ENTITY_NAME,
            $event->getNewsletterRecipient()->getId(),
            $event->getContext()
        );
    }
}