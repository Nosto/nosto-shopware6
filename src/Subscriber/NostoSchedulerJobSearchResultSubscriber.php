<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Subscriber;

use Nosto\NostoIntegration\Service\NostoSchedulerJobCountsProvider;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NostoSchedulerJobSearchResultSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly NostoSchedulerJobCountsProvider $jobCountsProvider,
    ) {
    }

    /**
     * @return array<string, string>
     */
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

            $entity->addExtension(
                'jobCounts',
                new ArrayStruct($counts, 'nosto_scheduler_job_counts'),
            );
        }
    }
}
