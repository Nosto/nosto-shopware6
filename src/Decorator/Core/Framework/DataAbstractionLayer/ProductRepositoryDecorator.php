<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Decorator\Core\Framework\DataAbstractionLayer;

use Nosto\NostoIntegration\Async\EventsWriter;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductRepositoryDecorator extends EntityRepository
{
    public function __construct(
        EntityDefinition $definition,
        EntityReaderInterface $reader,
        VersionManager $versionManager,
        EntitySearcherInterface $searcher,
        EntityAggregatorInterface $aggregator,
        EventDispatcherInterface $eventDispatcher,
        EntityLoadedEventFactory $eventFactory,
        private readonly EntityRepository $inner,
        private readonly EventsWriter $eventsWriter,
    ) {
        parent::__construct($definition, $reader, $versionManager, $searcher, $aggregator, $eventDispatcher, $eventFactory);
    }

    public function getDefinition(): EntityDefinition
    {
        return $this->inner->getDefinition();
    }

    public function aggregate(Criteria $criteria, Context $context): AggregationResultCollection
    {
        return $this->inner->aggregate($criteria, $context);
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        return $this->inner->searchIds($criteria, $context);
    }

    public function clone(
        string $id,
        Context $context,
        ?string $newId = null,
        ?CloneBehavior $behavior = null,
    ): EntityWrittenContainerEvent {
        return $this->inner->clone($id, $context, $newId, $behavior);
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->inner->search($criteria, $context);
    }

    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->inner->update($data, $context);
    }

    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->inner->upsert($data, $context);
    }

    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->inner->create($data, $context);
    }

    public function delete(array $ids, Context $context): EntityWrittenContainerEvent
    {
        // Store order numbers before removing the data
        $onlyIds = array_column($ids, 'id');
        $criteria = new Criteria($onlyIds);
        $iterator = new RepositoryIterator($this->inner, $context, $criteria);
        $orderNumberMapping = [];
        while (($result = $iterator->fetch()) !== null) {
            foreach ($result as $product) {
                $orderNumberMapping[$product->getId()] = $product->getProductNumber();
            }
        }

        $mainEvent = $this->inner->delete($ids, $context);

        // Register event for Nosto processing
        foreach ($mainEvent->getEvents() as $event) {
            if ($event instanceof EntityDeletedEvent && $event->getEntityName() === ProductDefinition::ENTITY_NAME) {
                foreach ($event->getIds() as $productId) {
                    if (!empty($orderNumberMapping[$productId])) {
                        $this->eventsWriter->writeEvent(
                            $event->getEntityName(),
                            $productId,
                            $event->getContext(),
                            $orderNumberMapping[$productId],
                        );
                    }
                }
            }
        }

        return $mainEvent;
    }

    public function createVersion(string $id, Context $context, ?string $name = null, ?string $versionId = null): string
    {
        return $this->inner->createVersion($id, $context, $name, $versionId);
    }

    public function merge(string $versionId, Context $context): void
    {
        $this->inner->merge($versionId, $context);
    }

    public function setEntityLoadedEventFactory(EntityLoadedEventFactory $eventFactory): void
    {
        if (method_exists($this->inner, 'setEntityLoadedEventFactory')) {
            $this->inner->setEntityLoadedEventFactory($eventFactory);
        }
    }
}
