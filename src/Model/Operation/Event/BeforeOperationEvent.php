<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Operation\Event;

use Nosto\Operation\AbstractOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class BeforeOperationEvent extends NestedEvent
{
    public function __construct(
        private readonly AbstractOperation $operation,
        private readonly Context $context,
    ) {
    }

    public function getOperation(): AbstractOperation
    {
        return $this->operation;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
