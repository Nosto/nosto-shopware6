<?php

declare(strict_types=1);

namespace Od\NostoIntegration\Model\Operation\Event;

use Nosto\Operation\AbstractOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class BeforeOperationEvent extends NestedEvent
{
    private AbstractOperation $operation;
    private Context $context;

    public function __construct(
        AbstractOperation $operation,
        Context $context
    ) {
        $this->operation = $operation;
        $this->context = $context;
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
