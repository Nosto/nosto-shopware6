<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class OrphanedJobRecoveryScheduledTask extends ScheduledTask
{
    private const EXECUTION_INTERVAL = 600;

    public static function getTaskName(): string
    {
        return 'nosto_integration_orphaned_job_recovery_task';
    }

    public static function getDefaultInterval(): int
    {
        return self::EXECUTION_INTERVAL;
    }
}
