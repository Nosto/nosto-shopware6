<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Person;

use Nosto\Model\AbstractPerson;

abstract class Builder implements BuilderInterface
{
    public function build(
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
    ): ?AbstractPerson {
        $person = $this->buildObject(
            $firstName,
            $lastName,
            $email,
            $phone,
            $postCode,
            $country,
            $customerGroup,
            $dateOfBirth,
            $gender,
            $customerReference,
        );

        return $person;
    }

    abstract public function buildObject(
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
    ): AbstractPerson;
}
