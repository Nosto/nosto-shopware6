<?php declare(strict_types=1);

namespace Od\NostoIntegration\Model\Nosto\Entity\Order\Buyer;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

class Builder
{
    public function build(OrderEntity $order)
    {
        $address = $order->getBillingAddress();
        $telephone = null;
        $postcode = null;
        $countryId = null;

        if ($address instanceof CustomerAddressEntity) {
            $telephone = $address->getPhoneNumber() ? (string)$address->getPhoneNumber() : null;
            $postcode = $address->getZipcode() ? (string)$address->getZipcode() : null;
            $countryId = $address->getCountryId() ? (string)$address->getCountryId() : null;
        }

        $customerFirstname = $order->getOrderCustomer()->getFirstName() ? (string)$order->getOrderCustomer()->getFirstName() : '';
        $customerLastname = $order->getOrderCustomer()->getLastName() ? (string)$order->getOrderCustomer()->getLastName() : '';
        $customerEmail = $order->getOrderCustomer()->getEmail() ? (string)$order->getOrderCustomer()->getEmail() : '';
    }
}