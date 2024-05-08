<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service\ScheduledTask;

use DateTime;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Throwable;

class OldJobCleanupScheduledTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        private readonly EntityRepository $jobRepository,
        private readonly ConfigProvider $configProvider,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($scheduledTaskRepository);
    }

    /**
     * @return ScheduledTask[]
     */
    public static function getHandledMessages(): iterable
    {
        return [OldJobCleanupScheduledTask::class];
    }

    public function run(): void
    {
        try {
            $isJobCleanupEnabled = $this->configProvider->isOldJobCleanupEnabled();
            $dayPeriod = $this->configProvider->getOldJobCleanupPeriod();

            if ($isJobCleanupEnabled && $dayPeriod) {
                $numberOfDaysBeforeToday = new DateTime(' - ' . $dayPeriod . ' day');

                // Here we have context-less process
                $context = new Context(new SystemSource());
                $criteria = new Criteria();
                $criteria->addFilter(
                    new AndFilter([
                        new RangeFilter(
                            'createdAt',
                            [
                                'lt' => $numberOfDaysBeforeToday->format(Defaults::STORAGE_DATE_FORMAT),
                            ],
                        ),
                        new ContainsFilter('type', 'nosto-integration'),
                        new EqualsFilter('parentId', null),
                    ]),
                );

                $idSearchResult = $this->jobRepository->searchIds($criteria, $context);

                //Formatting IDs array and deleting config keys
                $ids = array_map(static fn ($id): array => [
                    'id' => $id,
                ], $idSearchResult->getIds());

                $this->jobRepository->delete($ids, $context);
            }
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage());
        }
    }
}
