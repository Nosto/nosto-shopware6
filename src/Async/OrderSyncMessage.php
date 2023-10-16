<?php

namespace Nosto\NostoIntegration\Async;

use Nosto\NostoIntegration\Model\Operation\OrderSyncHandler;
use Nosto\Scheduler\Async\ParentAwareMessageInterface;
use Shopware\Core\Framework\Context;

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
        ?Context $context,
        ?string $name = null
    ) {
        parent::__construct($jobId, $context, $name);
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
