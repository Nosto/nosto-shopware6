<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class OldJobCleanupScheduledTask extends ScheduledTask
{
    private const EXECUTION_INTERVAL = 86400;

    public static function getTaskName(): string
    {
        return 'nosto_integration_old_job_cleanup_task';
    }

    public static function getDefaultInterval(): int
    {
        return self::EXECUTION_INTERVAL;
    }
}
