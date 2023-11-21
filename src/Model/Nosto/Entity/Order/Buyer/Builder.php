<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Order\Buyer;

use Nosto\Model\AbstractPerson;
use Nosto\Model\Order\Buyer;
use Nosto\NostoIntegration\Model\Nosto\Entity\Person\Builder as PersonBuilder;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

class Builder extends PersonBuilder
{
    public function buildObject(
        $firstName,
        $lastName,
        $email,
        $phone = null,
        $postCode = null,
        $country = null,
        $customerGroup = null,
        $dateOfBirth = null,
        $gender = null,
        $customerReference = null,
    ): Buyer {
        $buyer = new Buyer();
        $buyer->setFirstName($firstName);
        $buyer->setLastName($lastName);
        $buyer->setEmail($email);
        $buyer->setPhone($phone);
        $buyer->setPostCode($postCode);
        $buyer->setCountry($country);

        return $buyer;
    }

    public function fromOrder(OrderEntity $order): ?AbstractPerson
    {
        $address = $order->getBillingAddress();
        $telephone = null;
        $postcode = null;
        $countryId = null;
        if ($address instanceof OrderAddressEntity) {
            $telephone = $address->getPhoneNumber() ? (string) $address->getPhoneNumber() : null;
            $postcode = $address->getZipcode() ?: null;
            $countryId = $address->getCountryId() ?: null;
        }
        $customerFirstname = $order->getOrderCustomer()->getFirstName() ?: '';
        $customerLastname = $order->getOrderCustomer()->getLastName() ?: '';
        $customerEmail = $order->getOrderCustomer()->getEmail() ?: '';

        return $this->build(
            $customerFirstname,
            $customerLastname,
            $customerEmail,
            $telephone,
            $postcode,
            $countryId,
        );
    }
}
