<?php declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Customer;

use Nosto\Model\Customer;
use Nosto\NostoIntegration\Model\Nosto\Entity\Customer\Event\NostoCustomerBuiltEvent;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute as Newsletter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class Builder implements BuilderInterface
{
    private EntityRepository $newsletterRecipientRepository;
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(EntityRepository $newsletterRecipientRepository, EventDispatcherInterface $eventDispatcher)
    {
        $this->newsletterRecipientRepository = $newsletterRecipientRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function build(CustomerEntity $customer, Context $context): Customer
    {
        $nostoCustomer = new Customer();
        $nostoCustomer->setEmail($customer->getEmail());
        $nostoCustomer->setFirstName($customer->getFirstName());
        $nostoCustomer->setLastName($customer->getLastName());
        $nostoCustomer->setCustomerReference($this->generateCustomerReference($customer));
        $nostoCustomer->setMarketingPermission($this->hasMarketingPermission($customer, $context));
        $this->eventDispatcher->dispatch(new NostoCustomerBuiltEvent($customer, $nostoCustomer, $context));

        return $nostoCustomer;
    }

    private function hasMarketingPermission(CustomerEntity $customer, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $customer->getEmail()));
        $subscriber = $this->newsletterRecipientRepository->search($criteria, $context)->first();

        return $subscriber !== null
            ? in_array($subscriber->getStatus(), [Newsletter::OPTION_DIRECT, Newsletter::STATUS_OPT_IN])
            : false;
    }

    public static function generateCustomerReference(CustomerEntity $customer): string
    {
        return hash('sha256', $customer->getId() . $customer->getEmail());
    }
}
