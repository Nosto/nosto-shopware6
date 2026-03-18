<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Subscriber;

use Nosto\NostoIntegration\Service\NostoSchedulerJobCountsProvider;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NostoSchedulerJobSearchResultSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly NostoSchedulerJobCountsProvider $jobCountsProvider,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'nosto_scheduler_job.search.result.loaded' => 'onSearchResultLoaded',
        ];
    }

    public function onSearchResultLoaded(EntitySearchResultLoadedEvent $event): void
    {
        $entities = $event->getResult()->getEntities();
        if ($entities->count() === 0) {
            return;
        }

        $jobIds = $entities->getIds();
        if ($jobIds === []) {
            return;
        }

        $countsByJobId = $this->jobCountsProvider->getCountsByJobIds($jobIds);

        foreach ($entities as $entity) {
            $jobId = strtolower((string) $entity->getUniqueIdentifier());
            $counts = $countsByJobId[$jobId] ?? NostoSchedulerJobCountsProvider::createEmptyCounts();

            $entity->addArrayExtension('jobCounts', $counts);
        }
    }
}
