<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Async;

use Nosto\NostoIntegration\Model\Operation\OrderSyncHandler;
use Nosto\Scheduler\Async\ParentAwareMessageInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

class OrderSyncMessage extends AbstractMessage implements ParentAwareMessageInterface, AsyncMessageInterface
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

    /**
     * @return string[]
     */
    public function getNewOrderIds(): array
    {
        return $this->newOrderIds;
    }

    /**
     * @return string[]
     */
    public function getUpdatedOrderIds(): array
    {
        return $this->updatedOrderIds;
    }

    public function getParentJobId(): string
    {
        return $this->parentJobId;
    }
}
