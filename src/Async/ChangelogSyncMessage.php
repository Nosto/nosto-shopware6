<?php

namespace Od\NostoIntegration\Async;

use Od\NostoIntegration\Service\ScheduledTask\EntityChangelogScheduledTaskHandler;

class ChangelogSyncMessage extends AbstractMessage
{
    protected static string $defaultName = 'Changelog Entity Sync Operation';

    public function getHandlerCode(): string
    {
        return EntityChangelogScheduledTaskHandler::HANDLER_CODE;
    }
}