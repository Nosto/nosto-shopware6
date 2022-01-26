<?php

namespace Od\NostoIntegration\Async;

use Od\NostoIntegration\Model\Operation\ProductSyncHandler;
use Od\Scheduler\Async\ParentAwareMessageInterface;

class ProductSyncMessage extends AbstractMessage implements ParentAwareMessageInterface
{
    protected static string $defaultName = 'Product Sync Operation';
    private string $parentJobId;
    private array $productIds;

    public function __construct(
        string $jobId,
        string $parentJobId,
        array $productIds,
        ?string $name = null
    ) {
        parent::__construct($jobId, $name);
        $this->productIds = $productIds;
        $this->parentJobId = $parentJobId;
    }

    public function getHandlerCode(): string
    {
        return ProductSyncHandler::HANDLER_CODE;
    }

    public function getProductIds(): array
    {
        return $this->productIds;
    }

    public function getParentJobId(): string
    {
        return $this->parentJobId;
    }
}