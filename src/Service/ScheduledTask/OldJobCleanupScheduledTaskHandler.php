<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service\ScheduledTask;

use DateTime;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use function array_map;

class OldJobCleanupScheduledTaskHandler extends ScheduledTaskHandler
{
    private EntityRepository $jobRepository;

    public function __construct(
        EntityRepository $scheduledTaskRepository,
        EntityRepository $jobRepository,
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
        // Here we have context-less process
        $context = new Context(new SystemSource());
        $numberOfDaysBeforeToday = new DateTime(' - 5 day'); // needed to add to the plugin`s configuration
        $criteria = new Criteria();
        $criteria->addFilter(
            new RangeFilter(
                'createdAt',
                [
                    'lt' => $numberOfDaysBeforeToday->format(Defaults::STORAGE_DATE_FORMAT),
                ],
            ),
        );

        $idSearchResult = $this->jobRepository->searchIds($criteria, $context);

        //Formatting IDs array and deleting config keys
        $ids = array_map(static function ($id) {
            return [
                'id' => $id,
            ];
        }, $idSearchResult->getIds());

        $this->jobRepository->delete($ids, $context);
    }
}
