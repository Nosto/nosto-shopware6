<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service\ScheduledTask;

use DateTime;
use Doctrine\DBAL\Connection;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Throwable;

class OldNostoDataCleanupScheduledTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        private readonly Connection $connection,
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

                $this->connection->executeStatement(
                    'DELETE FROM nosto_integration_checkout_mapping WHERE created_at <= :timestamp',
                    ['timestamp' => $numberOfMonthsBeforeToday->format(Defaults::STORAGE_DATE_FORMAT)]
                );
            }
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage());
        }
    }
}
