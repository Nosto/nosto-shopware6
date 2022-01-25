<?php

namespace Od\NostoIntegration\Model\Operation;

use Od\NostoIntegration\Async\MarketingPermissionSyncMessage;
use Od\Scheduler\Model\Job\JobHandlerInterface;
use Od\Scheduler\Model\Job\JobResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;

class MarketingPermissionSyncHandler implements JobHandlerInterface
{
    public const HANDLER_CODE = 'od-nosto-marketing-permission-sync';
    private EntityRepositoryInterface $newsletterRecipientRepository;

    public function __construct(EntityRepositoryInterface $newsletterRecipientRepository)
    {
        $this->newsletterRecipientRepository = $newsletterRecipientRepository;
    }

    /**
     * @param MarketingPermissionSyncMessage $message
     *
     * @return JobResult
     */
    public function execute(object $message): JobResult
    {
        $newsletterRecipientIds = $message->getNewsletterRecipientIds();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $newsletterRecipientIds));
        $context = Context::createDefaultContext();
        $recipients = $this->newsletterRecipientRepository->search($criteria, $context);

        return new JobResult();
    }
}