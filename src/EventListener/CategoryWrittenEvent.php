<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\EventListener;

use Nosto\NostoIntegration\Async\EventsWriter;
use Shopware\Core\Content\Category\CategoryEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CategoryWrittenEvent implements EventSubscriberInterface
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
            CategoryEvents::CATEGORY_WRITTEN_EVENT => 'onCategoryWritten',
        ];
    }

    public function onCategoryWritten(EntityWrittenEvent $event): void
    {
        $this->writeEvents($event->getIds(), $event->getEntityName(), $event->getContext());
    }

    private function writeEvents(array $ids, string $entityName, Context $context): void
    {
        foreach ($ids as $categoryId) {
            $this->eventsWriter->writeEvent(
                $entityName,
                $categoryId,
                $context,
            );
        }
    }
}
