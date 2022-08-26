<?php

declare(strict_types=1);

namespace Od\NostoIntegration\Model\Nosto\Entity\Customer\Event;

use Nosto\Model\Customer as NostoCustomer;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class NostoCustomerBuiltEvent extends NestedEvent
{
    private CustomerEntity $customer;
    private NostoCustomer $nostoCustomer;
    private Context $context;

    public function __construct(
        CustomerEntity $customer,
        NostoCustomer $nostoCustomer,
        Context $context
    ) {
        $this->customer = $customer;
        $this->nostoCustomer = $nostoCustomer;
        $this->context = $context;
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
