<?php

namespace Od\NostoIntegration\Twig\Extension;

use Od\NostoIntegration\Model\Nosto\Entity\Customer\BuilderInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CustomerExtension extends AbstractExtension
{
    private BuilderInterface $builder;

    public function __construct(BuilderInterface $builder)
    {
        $this->builder = $builder;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('od_nosto_customer', [$this, 'getNostoCustomer'])
        ];
    }

    public function getNostoCustomer(CustomerEntity $customer, Context $context)
    {
        return $this->builder->build($customer, $context);
    }
}
