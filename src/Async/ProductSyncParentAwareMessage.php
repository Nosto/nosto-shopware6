<?php

namespace Od\NostoIntegration\Async;


use Od\Scheduler\Async\ParentAwareMessageInterface;

class ProductSyncParentAwareMessage extends ProductSyncMessage implements ParentAwareMessageInterface
{
    private string $parentJobId;

    public function __construct(
        string $jobId,
        string $parentJobId,
        array $productIds,
        ?string $name = null
    ) {
        parent::__construct($jobId, $productIds, $name);
        $this->parentJobId = $parentJobId;
    }

    public function getParentJobId(): string
    {
        return $this->parentJobId;
    }
}