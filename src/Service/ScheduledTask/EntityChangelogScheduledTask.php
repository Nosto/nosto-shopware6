<?php

namespace Od\NostoIntegration\Service\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class EntityChangelogScheduledTask extends ScheduledTask
{
    private const EXECUTION_INTERVAL = '86400';

    public static function getTaskName(): string
    {
        return 'od_nosto_entity_changelog_task';
    }

    public static function getDefaultInterval(): int
    {
        return self::EXECUTION_INTERVAL;
    }
}
