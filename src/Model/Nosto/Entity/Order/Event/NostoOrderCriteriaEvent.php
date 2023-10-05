<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Order\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\NestedEvent;

class NostoOrderCriteriaEvent extends NestedEvent
{
    private Criteria $criteria;
    private Context $context;

    public function __construct(
        Criteria $criteria,
        Context $context
    ) {
        $this->criteria = $criteria;
        $this->context = $context;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
