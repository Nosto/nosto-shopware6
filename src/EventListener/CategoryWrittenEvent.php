<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\EventListener;

use Nosto\NostoIntegration\Async\EventsWriter;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\CategoryEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CategoryWrittenEvent implements EventSubscriberInterface
{
    public function __construct(
        private readonly EventsWriter $eventsWriter,
        private readonly EntityRepository $categoryRepository,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CategoryEvents::CATEGORY_WRITTEN_EVENT => 'onCategoryWritten',
            EntityWriteEvent::class => 'beforeCategoryWrite',
        ];
    }

    public function onCategoryWritten(EntityWrittenEvent $event): void
    {
        $this->writeEvents($event->getIds(), $event->getEntityName(), $event->getContext());
    }

    public function beforeCategoryWrite(EntityWriteEvent $event): void
    {
        $categoryIds = array_values(array_filter(
            $event->getIds(CategoryDefinition::ENTITY_NAME),
            static fn (mixed $id): bool => is_string($id),
        ));
        if ($categoryIds === []) {
            return;
        }

        $context = $event->getContext();
        $previousProductStreamIds = $this->loadProductStreamIds($categoryIds, $context);

        $event->addSuccess(function () use ($categoryIds, $context, $previousProductStreamIds): void {
            $currentProductStreamIds = $this->loadProductStreamIds($categoryIds, $context);

            $this->writeEvents(
                array_values(array_unique([...$previousProductStreamIds, ...$currentProductStreamIds])),
                EventsWriter::DYNAMIC_CATEGORY_PRODUCT_STREAM_ENTITY_NAME,
                $context,
            );
        });
    }

    /**
     * @param list<string> $categoryIds
     * @return list<string>
     */
    private function loadProductStreamIds(array $categoryIds, Context $context): array
    {
        $categories = $this->categoryRepository->search(new Criteria($categoryIds), $context);
        $productStreamIds = [];
        /** @var CategoryEntity $category */
        foreach ($categories as $category) {
            $productStreamId = $category->getProductStreamId();
            if ($productStreamId !== null) {
                $productStreamIds[$productStreamId] = true;
            }
        }

        return array_keys($productStreamIds);
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
