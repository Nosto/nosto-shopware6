<?php

namespace Nosto\NostoIntegration\Async;

use Nosto\NostoIntegration\Model\Operation\EntityChangelogSyncHandler;

class EntityChangelogSyncMessage extends AbstractMessage
{
    protected static string $defaultName = 'Changelog Entity Sync Operation';

    public function getHandlerCode(): string
    {
        return EntityChangelogSyncHandler::HANDLER_CODE;
    }
}
