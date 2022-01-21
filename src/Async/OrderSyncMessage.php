<?php

namespace Od\NostoIntegration\Async;

use Od\NostoIntegration\Model\Operation\OrderSyncHandler;

class OrderSyncMessage extends AbstractMessage
{
    protected static string $defaultName = 'Order Sync Operation';
    private array $orderIds;

    public function __construct(
        string $jobId,
        array $orderIds,
        ?string $name = null
    ) {
        parent::__construct($jobId, $name);
        $this->orderIds = $orderIds;
    }

    public function getHandlerCode(): string
    {
        return OrderSyncHandler::HANDLER_CODE;
    }

    public function getOrderIds(): array
    {
        return $this->orderIds;
    }
}