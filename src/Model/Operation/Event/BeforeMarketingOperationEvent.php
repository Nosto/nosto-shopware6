<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Operation\Event;

use Nosto\Operation\AbstractOperation;
use Shopware\Core\Framework\Context;

class BeforeMarketingOperationEvent extends BeforeOperationEvent
{
    private array $payload;

    public function __construct(AbstractOperation $operation, array $payload, Context $context)
    {
        parent::__construct($operation, $context);
        $this->payload = $payload;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }
}
