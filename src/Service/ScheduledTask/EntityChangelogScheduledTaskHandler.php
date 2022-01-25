<?php

declare(strict_types=1);

namespace Od\NostoIntegration\Service\ScheduledTask;

use Od\NostoIntegration\Async\EntityChangelogSyncMessage;
use Od\Scheduler\Model\Job\GeneratingHandlerInterface;
use Od\Scheduler\Model\JobScheduler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\Uuid\Uuid;

class EntityChangelogScheduledTaskHandler extends ScheduledTaskHandler implements GeneratingHandlerInterface
{
    private JobScheduler $jobScheduler;

    public function __construct(
        EntityRepositoryInterface $scheduledTaskRepository,
        JobScheduler $jobScheduler
    ) {
        parent::__construct($scheduledTaskRepository);
        $this->jobScheduler = $jobScheduler;
    }

    public static function getHandledMessages(): iterable
    {
        return [EntityChangelogScheduledTask::class];
    }

    public function run(): void
    {
        $jobMessage = new EntityChangelogSyncMessage(Uuid::randomHex());
        $this->jobScheduler->schedule($jobMessage);
    }
}