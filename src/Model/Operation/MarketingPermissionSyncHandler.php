<?php

namespace Od\NostoIntegration\Model\Operation;

use Nosto\Operation\MarketingPermission;
use Nosto\Types\Signup\AccountInterface;
use Od\NostoIntegration\Async\MarketingPermissionSyncMessage;
use Od\NostoIntegration\Model\Nosto\Account;
use Od\NostoIntegration\Model\Operation\Event\BeforeMarketingOperationEvent;
use Od\Scheduler\Model\Job\{JobHandlerInterface, JobResult};
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\{EntityCollection, EntityRepository};
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class MarketingPermissionSyncHandler implements JobHandlerInterface
{
    public const HANDLER_CODE = 'od-nosto-marketing-permission-sync';
    private EntityRepository $newsletterRecipientRepository;
    private Account\Provider $accountProvider;
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        EntityRepository $newsletterRecipientRepository,
        Account\Provider $accountProvider,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->newsletterRecipientRepository = $newsletterRecipientRepository;
        $this->accountProvider = $accountProvider;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param MarketingPermissionSyncMessage $message
     *
     * @return JobResult
     */
    public function execute(object $message): JobResult
    {
        $operationResult = new JobResult();
        foreach ($this->accountProvider->all($message->getContext()) as $account) {
            $nostoAccount = $account->getNostoAccount();
            $accountOperationResult = $this->doOperation(
                $nostoAccount,
                $message->getContext(),
                $message->getNewsletterRecipientIds()
            );
            foreach ($accountOperationResult->getErrors() as $error) {
                $operationResult->addError($error);
            }
        }

        return $operationResult;
    }

    private function doOperation(AccountInterface $account, Context $context, array $subscriberIds): JobResult
    {
        $operation = new MarketingPermission($account);
        $result = new JobResult();
        foreach ($this->getSubscribers($context, $subscriberIds) as $subscriber) {
            $isSubscribed = in_array($subscriber->getStatus(),
                [
                    NewsletterSubscribeRoute::OPTION_DIRECT,
                    NewsletterSubscribeRoute::STATUS_OPT_IN
                ]
            );
            try {
                $this->eventDispatcher->dispatch(
                    new BeforeMarketingOperationEvent(
                        $operation,
                        ['email' => $subscriber->getEmail(), 'isSubscribed' => $isSubscribed], $context
                    )
                );
                $operation->update($subscriber->getEmail(), $isSubscribed);
            } catch (\Throwable $e) {
                $result->addError($e);
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
