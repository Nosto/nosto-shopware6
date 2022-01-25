<?php

namespace Od\NostoIntegration\Async;

use Od\NostoIntegration\Model\Operation\MarketingPermissionSyncHandler;
use Od\Scheduler\Async\ParentAwareMessageInterface;

class MarketingPermissionSyncMessage extends AbstractMessage implements ParentAwareMessageInterface

{
    protected static string $defaultName = 'Marketing Permission Sync Operation';
    private string $parentJobId;
    private array $subscriberIds;

    public function __construct(
        string $jobId,
        string $parentJobId,
        array $subscriberIds,
        ?string $name = null
    ) {
        parent::__construct($jobId, $name);
        $this->subscriberIds = $subscriberIds;
        $this->parentJobId = $parentJobId;
    }

    public function getHandlerCode(): string
    {
        return MarketingPermissionSyncHandler::HANDLER_CODE;
    }

    public function getNewsletterRecipientIds(): array
    {
        return $this->subscriberIds;
    }

    public function getParentJobId(): string
    {
        return $this->parentJobId;
    }
}
