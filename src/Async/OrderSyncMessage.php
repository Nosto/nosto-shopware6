<?php

namespace Od\NostoIntegration\Async;

use Od\NostoIntegration\Model\Operation\OrderSyncHandler;
use Od\Scheduler\Async\ParentAwareMessageInterface;

class OrderSyncMessage extends AbstractMessage implements ParentAwareMessageInterface
{
    protected static string $defaultName = 'Order Sync Operation';
    private array $newOrderIds;
    private array $updatedOrderIds;
    private string $parentJobId;

    public function __construct(
        string $jobId,
        string $parentJobId,
        array $newOrderIds,
        array $updatedOrderIds,
        ?string $name = null
    ) {
        parent::__construct($jobId, $name);
        $this->newOrderIds = $newOrderIds;
        $this->updatedOrderIds = $updatedOrderIds;
        $this->parentJobId = $parentJobId;
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