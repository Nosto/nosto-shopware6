<?php

namespace Od\NostoIntegration\Twig\Extension;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CustomerExtension extends AbstractExtension
{

    const CUSTOMER_REFERENCE_HASH_ALGO = 'sha256';

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('od_nosto_customer_reference', [$this, 'generateCustomerReference'])
        ];
    }

    /**
     * @param CustomerEntity $customer
     * @return string
     */
    public function generateCustomerReference(CustomerEntity $customer): string
    {
        return hash(
            self::CUSTOMER_REFERENCE_HASH_ALGO,
            $customer->getId() . $customer->getEmail()
        );
    }
}
