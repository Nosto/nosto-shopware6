<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\EventListener;

use Nosto\NostoIntegration\Async\EventsWriter;
use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterUnsubscribeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NewsletterEventListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly EventsWriter $eventsWriter,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            NewsletterConfirmEvent::class => 'onNewsletterConfirm',
            NewsletterUnsubscribeEvent::class => 'onNewsletterUnsubscribe',
        ];
    }

    public function onNewsletterConfirm(NewsletterConfirmEvent $event): void
    {
        $this->eventsWriter->writeEvent(
            $this->eventsWriter::NEWSLETTER_ENTITY_NAME,
            $event->getNewsletterRecipient()->getId(),
            $event->getContext(),
        );
    }

    public function onNewsletterUnsubscribe(NewsletterUnsubscribeEvent $event): void
    {
        $this->eventsWriter->writeEvent(
            $this->eventsWriter::NEWSLETTER_ENTITY_NAME,
            $event->getNewsletterRecipient()->getId(),
            $event->getContext(),
        );
    }
}
