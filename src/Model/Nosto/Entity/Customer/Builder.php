<?php declare(strict_types=1);

namespace Od\NostoIntegration\Model\Nosto\Entity\Customer;

use Nosto\Model\Customer;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute as Newsletter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class Builder
{
    private EntityRepositoryInterface $newsletterRecipientRepository;

    public function __construct(EntityRepositoryInterface $newsletterRecipientRepository)
    {
        $this->newsletterRecipientRepository = $newsletterRecipientRepository;
    }

    public function build(CustomerEntity $customer): Customer
    {
        $nostoCustomer = new Customer();
        $nostoCustomer->setEmail($customer->getEmail());
        $nostoCustomer->setFirstName($customer->getFirstName());
        $nostoCustomer->setLastName($customer->getLastName());
        $nostoCustomer->setCustomerReference($this->generateCustomerReference($customer));
        $nostoCustomer->setMarketingPermission($this->hasMarketingPermission($customer));

        return $nostoCustomer;
    }

    private function hasMarketingPermission(CustomerEntity $customer): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $customer->getEmail()));
        $subscriber = $this->newsletterRecipientRepository->search($criteria, Context::createDefaultContext())->first();

        return $subscriber !== null
            ? in_array($subscriber->getStatus(), [Newsletter::OPTION_DIRECT, Newsletter::STATUS_OPT_IN])
            : false;
    }

    public static function generateCustomerReference(CustomerEntity $customer): string
    {
        return hash('sha256', $customer->getId() . $customer->getEmail());
    }
}