<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Async;

use Nosto\NostoIntegration\Model\Operation\ExchangeRateSyncHandler;
use Nosto\Scheduler\Async\ParentAwareMessageInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

class ExchangeRateSyncMessage extends AbstractMessage implements ParentAwareMessageInterface, AsyncMessageInterface
{
    protected static string $defaultName = 'Exchange Rate Sync Operation';

    public function __construct(
        string $jobId,
        private readonly string $parentJobId,
        ?Context $context = null,
        ?string $name = null,
    ) {
        parent::__construct($jobId, $context, $name);
    }

    public function getHandlerCode(): string
    {
        return ExchangeRateSyncHandler::HANDLER_CODE;
    }

    public function getParentJobId(): string
    {
        return $this->parentJobId;
    }
}
