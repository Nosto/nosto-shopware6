<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Customer\Event;

use Nosto\Model\Customer as NostoCustomer;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class NostoCustomerBuiltEvent extends NestedEvent
{
    public function __construct(
        private readonly CustomerEntity $customer,
        private readonly NostoCustomer $nostoCustomer,
        private readonly Context $context,
    ) {
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function getNostoCustomer(): NostoCustomer
    {
        return $this->nostoCustomer;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
