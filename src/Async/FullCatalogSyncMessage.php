<?php

namespace Od\NostoIntegration\Async;

use Od\NostoIntegration\Model\Operation\FullCatalogSyncHandler;

class FullCatalogSyncMessage extends AbstractMessage
{
    protected static string $defaultName = 'Full Catalog Sync Operation';

    public function getHandlerCode(): string
    {
        return FullCatalogSyncHandler::HANDLER_CODE;
    }
}