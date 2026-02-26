<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\EventListener;

use Nosto\NostoIntegration\Async\EventsWriter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\System\Currency\CurrencyEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CurrencyWrittenEvent implements EventSubscriberInterface
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
            CurrencyEvents::CURRENCY_WRITTEN_EVENT => 'onCurrencyWritten',
        ];
    }

    public function onCurrencyWritten(EntityWrittenEvent $event): void
    {
        $this->writeEvents($event->getIds(), $event->getContext());
    }

    /**
     * @param string[] $ids
     */
    private function writeEvents(array $ids, Context $context): void
    {
        foreach ($ids as $currencyId) {
            $this->eventsWriter->writeEvent(
                EventsWriter::EXCHANGE_RATE_ENTITY_NAME,
                $currencyId,
                $context,
            );
        }
    }
}
