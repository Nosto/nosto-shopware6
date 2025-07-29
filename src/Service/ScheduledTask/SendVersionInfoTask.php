<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class SendVersionInfoTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'nosto_integration_send_version_info_task';
    }

    public static function getDefaultInterval(): int
    {
        // Run once a day = every 24 hours
        // 24 hours * 60 minutes * 60 seconds = 86,400 seconds
        return 86400;
    }
}
