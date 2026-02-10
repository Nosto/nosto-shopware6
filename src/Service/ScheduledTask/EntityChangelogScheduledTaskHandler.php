<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service\ScheduledTask;

use Nosto\NostoIntegration\Async\EntityChangelogSyncMessage;
use Nosto\NostoIntegration\Model\Operation\EntityChangelogSyncHandler;
use Nosto\Scheduler\Model\Job\GeneratingHandlerInterface;
use Nosto\Scheduler\Model\JobScheduler;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: EntityChangelogScheduledTask::class)]
class EntityChangelogScheduledTaskHandler extends ScheduledTaskHandler implements GeneratingHandlerInterface
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        private readonly JobScheduler $jobScheduler,
        private readonly LoggerInterface $logger,
        private readonly EntityRepository $nostoSchedulerJobRepository,
    ) {
        parent::__construct($scheduledTaskRepository, $this->logger);
    }

    public function run(): void
    {
        $context = Context::createCLIContext();
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('type', EntityChangelogSyncHandler::HANDLER_CODE))
            ->addFilter(new EqualsFilter('status', 'pending'))
            ->addFilter(new EqualsFilter('parentId', null))
            ->setLimit(1);

        // Only dispatch a new task if there is none pending
        if ($this->nostoSchedulerJobRepository->searchIds($criteria, $context)->firstId()) {
            return;
        }

        $jobMessage = new EntityChangelogSyncMessage(Uuid::randomHex());
        $this->jobScheduler->schedule($jobMessage);
    }
}
