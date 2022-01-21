<?php

namespace Od\NostoIntegration\Async;

use Od\NostoIntegration\Model\Operation\ProductSyncHandler;

class ProductSyncMessage extends AbstractMessage
{
    protected static string $defaultName = 'Product Sync Operation';
    private array $productIds;

    public function __construct(
        string $jobId,
        array $productIds,
        ?string $name = null
    ) {
        parent::__construct($jobId, $name);
        $this->productIds = $productIds;
    }

    public function getHandlerCode(): string
    {
        return ProductSyncHandler::HANDLER_CODE;
    }

    public function getProductIds(): array
    {
        return $this->productIds;
    }
}