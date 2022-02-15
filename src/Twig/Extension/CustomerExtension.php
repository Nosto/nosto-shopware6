<?php

namespace Od\NostoIntegration\Twig\Extension;

use Od\NostoIntegration\Model\Nosto\Entity\Customer;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CustomerExtension extends AbstractExtension
{
    private Customer\Builder $builder;

    public function __construct(Customer\Builder $builder)
    {
        $this->builder = $builder;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('od_nosto_customer', [$this, 'getNostoCustomer'])
        ];
    }

    public function getNostoCustomer(CustomerEntity $customer)
    {
        return $this->builder->build($customer);
    }
}
