<?php

declare(strict_types=1);

namespace Od\NostoIntegration\Service\ScheduledTask;

use DateTime;
use Od\NostoIntegration\Async\AbstractMessage;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use function array_map;

class OldJobCleanupScheduledTaskHandler extends ScheduledTaskHandler
{
    private EntityRepositoryInterface $jobRepository;

    public function __construct(
        EntityRepositoryInterface $scheduledTaskRepository,
        EntityRepositoryInterface $jobRepository
    ) {
        parent::__construct($scheduledTaskRepository);
        $this->jobRepository = $jobRepository;
    }

    public static function getHandledMessages(): iterable
    {
        return [OldJobCleanupScheduledTask::class];
    }

    public function run(): void
    {
        $context = AbstractMessage::createDefaultContext();
        $numberOfDaysBeforeToday = new DateTime(' - 5 day'); // needed to add to the plugin`s configuration
        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter(
            'createdAt',
            ['lt' => $numberOfDaysBeforeToday->format(Defaults::STORAGE_DATE_FORMAT)])
        );

        $idSearchResult = $this->jobRepository->searchIds($criteria, $context);

        //Formatting IDs array and deleting config keys
        $ids = array_map(static function ($id) {
            return ['id' => $id];
        }, $idSearchResult->getIds());

        $this->jobRepository->delete($ids, $context);
    }
}
