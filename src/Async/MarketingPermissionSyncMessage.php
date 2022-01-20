<?php

namespace Od\NostoIntegration\Async;

use Od\NostoIntegration\Model\Operation\MarketingPermissionSyncHandler;

class MarketingPermissionSyncMessage extends AbstractMessage

{
    protected static string $defaultName = 'Marketing Permission Sync Operation';
    private array $subscriberIds;

    public function __construct(
        string $jobId,
        array $subscriberIds,
        ?string $name = null
    ) {
        parent::__construct($jobId, $name);
        $this->subscriberIds = $subscriberIds;
    }

    public function getHandlerCode(): string

    {
        return MarketingPermissionSyncHandler::HANDLER_CODE;
    }

    public function getNewsletterRecipientIds(): array
    {
        return $this->subscriberIds;
    }
}
