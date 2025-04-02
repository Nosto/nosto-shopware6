<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\EventListener;

use Nosto\NostoIntegration\Async\EventsWriter;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CategoryWrittenDeletedEvent implements EventSubscriberInterface
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
            EntityDeleteEvent::class => 'beforeDelete',
        ];
    }

    public function onCategoryWritten(EntityWrittenEvent $event): void
    {
        $this->writeEvents($event->getIds(), $event->getEntityName(), $event->getContext());
    }

    public function beforeDelete(EntityDeleteEvent $event): void
    {
        $ids = $event->getIds(CategoryDefinition::ENTITY_NAME);

        if (count($ids)) {
            $event->addSuccess(function () use ($ids, $event): void {
                $this->writeEvents($ids, CategoryDefinition::ENTITY_NAME, $event->getContext());
            });
        }
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
