<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Service;

use Doctrine\DBAL\Connection;
use Nosto\NostoIntegration\Service\JobRecoveryService;
use Nosto\NostoIntegration\Service\QueuedJobIdProvider;
use Nosto\Scheduler\Model\Job\JobFailureHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Shopware\Core\Framework\Uuid\Uuid;

final class JobRecoveryServiceTest extends TestCase
{
    public function testRecoverFailsUnfinishedJobsWhoseMessageIsNoLongerQueued(): void
    {
        $lostJob = $this->createJobRow(true);
        $queuedJob = $this->createJobRow(true);
        $failedJobIds = [];
        $jobFailureHandler = $this->createMock(JobFailureHandler::class);
        $jobFailureHandler->method('fail')->willReturnCallback(
            static function (string $jobId) use (&$failedJobIds): void {
                $failedJobIds[] = $jobId;
            },
        );

        $service = $this->createService([$lostJob, $queuedJob], [
            $queuedJob['id'] => true,
        ], $jobFailureHandler);

        self::assertSame(1, $service->recoverOrphanedJobs());
        self::assertSame([$lostJob['id']], $failedJobIds);
    }

    public function testRecoverLeavesParentsWithCompletedChildGenerationToTheirChildren(): void
    {
        $parent = $this->createJobRow(false, true);
        $jobFailureHandler = $this->createMock(JobFailureHandler::class);
        $jobFailureHandler->expects($this->never())->method('fail');

        $service = $this->createService([$parent], [], $jobFailureHandler);

        self::assertSame(0, $service->recoverOrphanedJobs());
    }

    public function testRecoverFailsParentsThatStoppedWhileGeneratingChildren(): void
    {
        $parent = $this->createJobRow(false, false);
        $jobFailureHandler = $this->createMock(JobFailureHandler::class);
        $jobFailureHandler->expects($this->once())->method('fail')->with($parent['id']);

        $service = $this->createService([$parent], [], $jobFailureHandler);

        self::assertSame(1, $service->recoverOrphanedJobs());
    }

    public function testRecoverDoesNotInspectTheQueueWhenNothingIsUnfinished(): void
    {
        $queuedJobIdProvider = $this->createMock(QueuedJobIdProvider::class);
        $queuedJobIdProvider->method('isAvailable')->willReturn(true);
        $queuedJobIdProvider->expects($this->never())->method('getJobIds');
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $service = new JobRecoveryService(
            $connection,
            $this->createMock(JobFailureHandler::class),
            $queuedJobIdProvider,
            $this->createMock(LoggerInterface::class),
        );

        self::assertSame(0, $service->recoverOrphanedJobs());
    }

    public function testRecoverDoesNothingWhenTheQueueCannotBeInspected(): void
    {
        $queuedJobIdProvider = $this->createMock(QueuedJobIdProvider::class);
        $queuedJobIdProvider->method('isAvailable')->willReturn(false);
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');
        $jobFailureHandler = $this->createMock(JobFailureHandler::class);
        $jobFailureHandler->expects($this->never())->method('fail');

        $service = new JobRecoveryService(
            $connection,
            $jobFailureHandler,
            $queuedJobIdProvider,
            $this->createMock(LoggerInterface::class),
        );

        self::assertSame(0, $service->recoverOrphanedJobs());
    }

    public function testRecoverNeverThrowsWhenTheRecoveryItselfFails(): void
    {
        $queuedJobIdProvider = $this->createMock(QueuedJobIdProvider::class);
        $queuedJobIdProvider->method('isAvailable')->willReturn(true);
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willThrowException(new RuntimeException('Connection lost'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $service = new JobRecoveryService(
            $connection,
            $this->createMock(JobFailureHandler::class),
            $queuedJobIdProvider,
            $logger,
        );

        self::assertSame(0, $service->recoverOrphanedJobs());
    }

    public function testFailUnfinishedJobsFailsChildrenBeforeTheirParent(): void
    {
        $parent = $this->createJobRow(false, true);
        $firstChild = $this->createJobRow(true);
        $secondChild = $this->createJobRow(true);
        $failures = [];
        $jobFailureHandler = $this->createMock(JobFailureHandler::class);
        $jobFailureHandler->method('fail')->willReturnCallback(
            static function (string $jobId, string $reason) use (&$failures): void {
                $failures[] = [$jobId, $reason];
            },
        );

        $service = $this->createService([$parent, $firstChild, $secondChild], [], $jobFailureHandler);

        self::assertSame(3, $service->failUnfinishedJobs('Plugin deactivated'));
        self::assertSame(
            [
                [$firstChild['id'], 'Plugin deactivated'],
                [$secondChild['id'], 'Plugin deactivated'],
                [$parent['id'], 'Plugin deactivated'],
            ],
            $failures,
        );
    }

    public function testRecoverFailsOnlyAFixedNumberOfJobsPerRun(): void
    {
        $jobRows = array_map(fn (): array => $this->createJobRow(true), range(1, 501));
        $jobFailureHandler = $this->createMock(JobFailureHandler::class);
        $jobFailureHandler->expects($this->exactly(500))->method('fail');

        $service = $this->createService($jobRows, [], $jobFailureHandler);

        self::assertSame(500, $service->recoverOrphanedJobs());
    }

    public function testFailUnfinishedJobsFailsOnlyAFixedNumberOfJobsPerRun(): void
    {
        $jobRows = array_map(fn (): array => $this->createJobRow(true), range(1, 501));
        $jobFailureHandler = $this->createMock(JobFailureHandler::class);
        $jobFailureHandler->expects($this->exactly(500))->method('fail');

        $service = $this->createService($jobRows, [], $jobFailureHandler);

        self::assertSame(500, $service->failUnfinishedJobs('Plugin deactivated'));
    }

    public function testFailUnfinishedJobsNeverThrowsWhenTheCleanupFails(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willThrowException(new RuntimeException('Connection lost'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $service = new JobRecoveryService(
            $connection,
            $this->createMock(JobFailureHandler::class),
            $this->createMock(QueuedJobIdProvider::class),
            $logger,
        );

        self::assertSame(0, $service->failUnfinishedJobs('Plugin deactivated'));
    }

    /**
     * @param list<array{id: string, has_parent: string, generation_completed: string}> $jobRows
     * @param array<string, true> $queuedJobIds
     */
    private function createService(
        array $jobRows,
        array $queuedJobIds,
        JobFailureHandler $jobFailureHandler,
    ): JobRecoveryService {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($jobRows);
        $queuedJobIdProvider = $this->createMock(QueuedJobIdProvider::class);
        $queuedJobIdProvider->method('isAvailable')->willReturn(true);
        $queuedJobIdProvider->method('getJobIds')->willReturn($queuedJobIds);

        return new JobRecoveryService(
            $connection,
            $jobFailureHandler,
            $queuedJobIdProvider,
            $this->createMock(LoggerInterface::class),
        );
    }

    /**
     * @return array{id: string, has_parent: string, generation_completed: string}
     */
    private function createJobRow(bool $hasParent, bool $generationCompleted = false): array
    {
        return [
            'id' => Uuid::randomHex(),
            'has_parent' => $hasParent ? '1' : '0',
            'generation_completed' => $generationCompleted ? '1' : '0',
        ];
    }
}
