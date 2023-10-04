<?php

namespace Od\NostoIntegration\Service\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class DailyProductSyncScheduledTask extends ScheduledTask
{
    private const EXECUTION_INTERVAL = '300';

    public static function getTaskName(): string
    {
        return 'nosto_integration_daily_product_sync_task';
    }

    public static function getDefaultInterval(): int
    {
        return self::EXECUTION_INTERVAL;
    }
}
