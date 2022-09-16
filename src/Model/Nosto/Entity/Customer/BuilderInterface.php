<?php

declare(strict_types=1);

namespace Od\NostoIntegration\Model\Nosto\Entity\Customer;

use Nosto\Model\Customer;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;

interface BuilderInterface
{
    public function build(CustomerEntity $customer, Context $context): Customer;
}
