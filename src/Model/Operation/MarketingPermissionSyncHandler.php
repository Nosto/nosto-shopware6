<?php

namespace Od\NostoIntegration\Model\Operation;

use Exception;
use Nosto\NostoException;
use Nosto\Operation\MarketingPermission;
use Nosto\Types\Signup\AccountInterface;
use Od\NostoIntegration\Async\MarketingPermissionSyncMessage;
use Od\NostoIntegration\Model\Nosto\Account;
use Od\Scheduler\Model\Job\JobHandlerInterface;
use Od\Scheduler\Model\Job\JobResult;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;

class MarketingPermissionSyncHandler implements JobHandlerInterface
{
    public const HANDLER_CODE = 'od-nosto-marketing-permission-sync';
    private EntityRepositoryInterface $newsletterRecipientRepository;
    private Account\Provider $accountProvider;

    public function __construct(
        EntityRepositoryInterface $newsletterRecipientRepository,
        Account\Provider $accountProvider
    ) {
        $this->newsletterRecipientRepository = $newsletterRecipientRepository;
        $this->accountProvider = $accountProvider;
    }

    /**
     * @param MarketingPermissionSyncMessage $message
     *
     * @return JobResult
     */
    public function execute(object $message): JobResult
    {
        $operationResult = new JobResult();
        $context = Context::createDefaultContext();
        foreach ($this->accountProvider->all() as $account) {
            $nostoAccount = $account->getNostoAccount();
            $accountOperationResult = $this->doOperation($nostoAccount, $context,
                $message->getNewsletterRecipientIds());
            foreach ($accountOperationResult->getErrors() as $error) {
                $operationResult->addError($error);
            }
        }

        return $operationResult;
    }

    private function doOperation(AccountInterface $account, Context $context, array $subscriberIds): JobResult
    {
        $operation = new MarketingPermission($account);
        foreach ($this->getSubscribers($context, $subscriberIds) as $subscriber) {
            $isSubscribed = $subscriber->getStatus() === NewsletterSubscribeRoute::OPTION_DIRECT || $subscriber->getStatus() === NewsletterSubscribeRoute::STATUS_OPT_IN;
            try {
                $operation->update(
                    $subscriber->getEmail(),
                    $isSubscribed
                );
            } catch (Exception $e) {
                throw new NostoException(
                    sprintf(
                        "Failed to update customer marketing permission.
                        Message was: %s",
                        $e->getMessage()
                    )
                );
            }
        }

        return new JobResult;
    }

    private function getSubscribers(Context $context, array $subscriberIds): EntityCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $subscriberIds));

        return $this->newsletterRecipientRepository->search($criteria, $context)->getEntities();
    }
}