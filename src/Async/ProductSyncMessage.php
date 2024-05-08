<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Async;

use Nosto\NostoIntegration\Model\Operation\ProductSyncHandler;
use Nosto\Scheduler\Async\ParentAwareMessageInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

class ProductSyncMessage extends AbstractMessage implements ParentAwareMessageInterface, AsyncMessageInterface
{
    protected static string $defaultName = 'Product Sync Operation';

    public function __construct(
        string $jobId,
        private readonly string $parentJobId,
        private readonly array $productIds,
        ?Context $context = null,
        ?string $name = null,
    ) {
        parent::__construct($jobId, $context, $name);
    }

    public function getHandlerCode(): string
    {
        return ProductSyncHandler::HANDLER_CODE;
    }

    /**
     * @return string[]
     */
    public function getProductIds(): array
    {
        return $this->productIds;
    }

    public function getParentJobId(): string
    {
        return $this->parentJobId;
    }
}
