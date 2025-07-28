<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Async;

use Nosto\NostoIntegration\Model\Operation\CategorySyncHandler;
use Nosto\Scheduler\Async\ParentAwareMessageInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

class CategorySyncMessage extends AbstractMessage implements ParentAwareMessageInterface, AsyncMessageInterface
{
    protected static string $defaultName = 'Category Sync Operation';

    public function __construct(
        string $jobId,
        private readonly string $parentJobId,
        private readonly array $categoryIds,
        ?Context $context = null,
        ?string $name = null,
    ) {
        parent::__construct($jobId, $context, $name);
    }

    public function getHandlerCode(): string
    {
        return CategorySyncHandler::HANDLER_CODE;
    }

    /**
     * @return string[]
     */
    public function getCategoryIds(): array
    {
        return $this->categoryIds;
    }

    public function getParentJobId(): string
    {
        return $this->parentJobId;
    }
}
