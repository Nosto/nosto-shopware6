<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Service\ScheduledTask;

use Nosto\NostoIntegration\Service\JobRecoveryService;
use Nosto\NostoIntegration\Service\ScheduledTask\OrphanedJobRecoveryScheduledTask;
use Nosto\NostoIntegration\Service\ScheduledTask\OrphanedJobRecoveryScheduledTaskHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

final class OrphanedJobRecoveryScheduledTaskHandlerTest extends TestCase
{
    public function testRunRecoversOrphanedJobs(): void
    {
        $jobRecoveryService = $this->createMock(JobRecoveryService::class);
        $jobRecoveryService->expects($this->once())
            ->method('recoverOrphanedJobs')
            ->willReturn(0);

        $handler = new OrphanedJobRecoveryScheduledTaskHandler(
            $this->createMock(EntityRepository::class),
            $jobRecoveryService,
            $this->createMock(LoggerInterface::class),
        );

        $handler->run();
    }

    public function testTaskHasAStableNameAndInterval(): void
    {
        self::assertSame(
            'nosto_integration_orphaned_job_recovery_task',
            OrphanedJobRecoveryScheduledTask::getTaskName(),
        );
        self::assertSame(600, OrphanedJobRecoveryScheduledTask::getDefaultInterval());
    }
}
