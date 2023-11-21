<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Async;

use Nosto\NostoIntegration\Model\Operation\MarketingPermissionSyncHandler;
use Nosto\Scheduler\Async\ParentAwareMessageInterface;
use Shopware\Core\Framework\Context;

class MarketingPermissionSyncMessage extends AbstractMessage implements ParentAwareMessageInterface
{
    protected static string $defaultName = 'Marketing Permission Sync Operation';

    private string $parentJobId;

    private array $newsletterRecipientIds;

    public function __construct(
        string $jobId,
        string $parentJobId,
        array $newsletterRecipientIds,
        ?Context $context = null,
        ?string $name = null,
    ) {
        parent::__construct($jobId, $context, $name);
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
