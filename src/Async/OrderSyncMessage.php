<?php

namespace Od\NostoIntegration\Async;

use Od\NostoIntegration\Model\Operation\OrderSyncHandler;
use Od\Scheduler\Async\ParentAwareMessageInterface;

class OrderSyncMessage extends AbstractMessage implements ParentAwareMessageInterface
{
    protected static string $defaultName = 'Order Sync Operation';
    private array $orderIds;
    private string $parentJobId;

    public function __construct(
        string $jobId,
        string $parentJobId,
        array $orderIds,
        ?string $name = null
    ) {
        parent::__construct($jobId, $name);
        $this->orderIds = $orderIds;
        $this->parentJobId = $parentJobId;
    }

    public function getHandlerCode(): string
    {
        return OrderSyncHandler::HANDLER_CODE;
    }

    public function getOrderIds(): array
    {
        return $this->orderIds;
    }

    public function getParentJobId(): string
    {
        return $this->parentJobId;
    }
}