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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Throwable;

class OldNostoDataCleanupScheduledTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        private readonly EntityRepository $mappingRepository,
        private readonly ConfigProvider $configProvider,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($scheduledTaskRepository);
    }

    public static function getHandledMessages(): iterable
    {
        return [OldNostoDataCleanupScheduledTask::class];
    }

    public function run(): void
    {
        try {
            $isNostoDataCleanupEnabled = $this->configProvider->isOldNostoDataCleanupEnabled();
            $monthPeriod = $this->configProvider->getOldNostoDataCleanupPeriod();

            if ($isNostoDataCleanupEnabled && $monthPeriod) {
                $numberOfMonthsBeforeToday = new DateTime(' - ' . $monthPeriod . ' month');

                // Here we have context-less process
                $context = new Context(new SystemSource());
                $criteria = new Criteria();
                $criteria->addFilter(
                    new AndFilter([
                        new RangeFilter(
                            'createdAt',
                            [
                                'lt' => $numberOfMonthsBeforeToday->format(Defaults::STORAGE_DATE_FORMAT),
                            ],
                        ),
                        new ContainsFilter('mapping_table', 'cart'),
                    ]),
                );

                $idSearchResult = $this->mappingRepository->searchIds($criteria, $context);

                // Formatting IDs array and deleting config keys
                $ids = array_map(static function ($id) {
                    return [
                        'id' => $id,
                    ];
                }, $idSearchResult->getIds());

                $this->mappingRepository->delete($ids, $context);
            }
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage());
        }
    }
}
