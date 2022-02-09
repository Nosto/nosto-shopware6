<?php

namespace Od\NostoIntegration\Async;

use Od\NostoIntegration\Model\Operation\MarketingPermissionSyncHandler;
use Od\Scheduler\Async\ParentAwareMessageInterface;

class MarketingPermissionSyncMessage extends AbstractMessage implements ParentAwareMessageInterface

{
    protected static string $defaultName = 'Marketing Permission Sync Operation';
    private string $parentJobId;
    private array $newsletterRecipientIds;

    public function __construct(
        string $jobId,
        string $parentJobId,
        array $newsletterRecipientIds,
        ?string $name = null
    ) {
        parent::__construct($jobId, $name);
        $this->newsletterRecipientIds = $newsletterRecipientIds;
        $this->parentJobId = $parentJobId;
    }

    public function getHandlerCode(): string
    {
        return MarketingPermissionSyncHandler::HANDLER_CODE;
    }

    public function getNewsletterRecipientIds(): array
    {
        return $this->newsletterRecipientIds;
    }

    public function getParentJobId(): string
    {
        return $this->parentJobId;
    }
}
