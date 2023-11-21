<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Async;

use Nosto\NostoIntegration\Model\Operation\ProductSyncHandler;
use Nosto\Scheduler\Async\ParentAwareMessageInterface;
use Shopware\Core\Framework\Context;

class ProductSyncMessage extends AbstractMessage implements ParentAwareMessageInterface
{
    protected static string $defaultName = 'Product Sync Operation';

    private string $parentJobId;

    private array $productIds;

    public function __construct(
        string $jobId,
        string $parentJobId,
        array $productIds,
        ?Context $context = null,
        ?string $name = null,
    ) {
        parent::__construct($jobId, $context, $name);
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
