<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Async;

use Nosto\NostoIntegration\Model\Operation\OrderSyncHandler;
use Nosto\Scheduler\Async\ParentAwareMessageInterface;
use Shopware\Core\Framework\Context;

class OrderSyncMessage extends AbstractMessage implements ParentAwareMessageInterface
{
    protected static string $defaultName = 'Order Sync Operation';

    public function __construct(
        string $jobId,
        private readonly string $parentJobId,
        private readonly array $newOrderIds,
        private readonly array $updatedOrderIds,
        ?Context $context,
        ?string $name = null,
    ) {
        parent::__construct($jobId, $context, $name);
    }

    public function getHandlerCode(): string
    {
        return OrderSyncHandler::HANDLER_CODE;
    }

    public function getNewOrderIds(): array
    {
        return $this->newOrderIds;
    }

    public function getUpdatedOrderIds(): array
    {
        return $this->updatedOrderIds;
    }

    public function getParentJobId(): string
    {
        return $this->parentJobId;
    }
}
