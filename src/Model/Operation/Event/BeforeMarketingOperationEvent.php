<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Operation\Event;

use Nosto\Operation\AbstractOperation;
use Shopware\Core\Framework\Context;

class BeforeMarketingOperationEvent extends BeforeOperationEvent
{
    public function __construct(
        AbstractOperation $operation,
        private readonly array $payload,
        Context $context,
    ) {
        parent::__construct($operation, $context);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }
}
