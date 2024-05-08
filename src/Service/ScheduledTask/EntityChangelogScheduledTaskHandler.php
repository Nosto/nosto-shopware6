<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service\ScheduledTask;

use Nosto\NostoIntegration\Async\EntityChangelogSyncMessage;
use Nosto\Scheduler\Model\Job\GeneratingHandlerInterface;
use Nosto\Scheduler\Model\JobScheduler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\Uuid\Uuid;

class EntityChangelogScheduledTaskHandler extends ScheduledTaskHandler implements GeneratingHandlerInterface
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        private readonly JobScheduler $jobScheduler,
    ) {
        parent::__construct($scheduledTaskRepository);
    }

    /**
     * @return ScheduledTask[]
     */
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
