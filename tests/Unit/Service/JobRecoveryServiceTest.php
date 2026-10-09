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
    public function testRecoverFailsChildJobsWhoseMessageIsNoLongerQueued(): void
    {
        $lostJobId = Uuid::randomHex();
        $queuedJobId = Uuid::randomHex();
        $failures = $this->recordFailures($jobFailureHandler);

        $service = $this->createService(
            $jobFailureHandler,
            children: [$lostJobId, $queuedJobId],
            queuedJobIds: [
                $queuedJobId => true,
            ],
        );

        self::assertSame(1, $service->recoverOrphanedJobs());
        self::assertSame([$lostJobId], array_column($failures(), 0));
        self::assertStringContainsString('queued message', $failures()[0][1]);
    }

    public function testRecoverDoesNotInspectTheQueueWhenThereAreNoOldChildJobs(): void
    {
        $queuedJobIdProvider = $this->createMock(QueuedJobIdProvider::class);
        $queuedJobIdProvider->method('isAvailable')->willReturn(true);
        $queuedJobIdProvider->expects($this->never())->method('getJobIds');

        $service = $this->createService(
            $this->createMock(JobFailureHandler::class),
            queuedJobIdProvider: $queuedJobIdProvider,
        );

        self::assertSame(0, $service->recoverOrphanedJobs());
    }

    public function testRecoverFailsParentsWhoseChildCreationStoppedWithoutLookingAtTheQueue(): void
    {
        $parentId = Uuid::randomHex();
        $queuedJobIdProvider = $this->createMock(QueuedJobIdProvider::class);
        $queuedJobIdProvider->method('isAvailable')->willReturn(true);
        $queuedJobIdProvider->expects($this->never())->method('getJobIds');
        $failures = $this->recordFailures($jobFailureHandler);

        $service = $this->createService(
            $jobFailureHandler,
            parents: [$parentId],
            queuedJobIdProvider: $queuedJobIdProvider,
        );

        self::assertSame(1, $service->recoverOrphanedJobs());
        self::assertSame([$parentId], array_column($failures(), 0));
        self::assertStringContainsString('child jobs', $failures()[0][1]);
    }

    public function testRecoverDoesNothingWhenTheQueueCannotBeInspected(): void
    {
        $queuedJobIdProvider = $this->createMock(QueuedJobIdProvider::class);
        $queuedJobIdProvider->method('isAvailable')->willReturn(false);
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchFirstColumn');
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

    public function testRecoverFailsNothingWhenTheQueueCannotBeRead(): void
    {
        $queuedJobIdProvider = $this->createMock(QueuedJobIdProvider::class);
        $queuedJobIdProvider->method('isAvailable')->willReturn(true);
        $queuedJobIdProvider->method('getJobIds')->willThrowException(new RuntimeException('Unreadable message'));
        $jobFailureHandler = $this->createMock(JobFailureHandler::class);
        $jobFailureHandler->expects($this->never())->method('fail');

        $service = $this->createService(
            $jobFailureHandler,
            children: [Uuid::randomHex()],
            queuedJobIdProvider: $queuedJobIdProvider,
        );

        self::assertSame(0, $service->recoverOrphanedJobs());
    }

    public function testRecoverNeverThrowsWhenTheRecoveryItselfFails(): void
    {
        $queuedJobIdProvider = $this->createMock(QueuedJobIdProvider::class);
        $queuedJobIdProvider->method('isAvailable')->willReturn(true);
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willThrowException(new RuntimeException('Connection lost'));
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

    public function testOnlyJobsThatWereActuallyFailedAreCounted(): void
    {
        $changedJobId = Uuid::randomHex();
        $skippedJobId = Uuid::randomHex();
        $jobFailureHandler = $this->createMock(JobFailureHandler::class);
        $jobFailureHandler->method('fail')->willReturnCallback(
            static fn (string $jobId): bool => $jobId === $changedJobId,
        );
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Marked 1 orphaned'));

        $service = $this->createService($jobFailureHandler, children: [$changedJobId, $skippedJobId], logger: $logger);

        self::assertSame(1, $service->recoverOrphanedJobs());
    }

    public function testRecoverFailsOnlyAFixedNumberOfJobsPerRun(): void
    {
        $children = array_map(static fn (): string => Uuid::randomHex(), range(1, 501));
        $jobFailureHandler = $this->createMock(JobFailureHandler::class);
        $jobFailureHandler->expects($this->exactly(500))->method('fail')->willReturn(true);

        $service = $this->createService($jobFailureHandler, children: $children);

        self::assertSame(500, $service->recoverOrphanedJobs());
    }

    public function testRecoveryOnlyLooksAtNostoJobs(): void
    {
        $queries = [];
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturnCallback(
            static function (string $query, array $params) use (&$queries): array {
                $queries[] = $params;

                return [];
            },
        );
        $queuedJobIdProvider = $this->createMock(QueuedJobIdProvider::class);
        $queuedJobIdProvider->method('isAvailable')->willReturn(true);
        $service = new JobRecoveryService(
            $connection,
            $this->createMock(JobFailureHandler::class),
            $queuedJobIdProvider,
            $this->createMock(LoggerInterface::class),
        );

        $service->recoverOrphanedJobs();
        $service->failUnfinishedJobs('Plugin deactivated');

        self::assertCount(3, $queries);
        foreach ($queries as $params) {
            self::assertSame('nosto-integration%', $params['typePrefix']);
        }
    }

    public function testFailUnfinishedJobsKeepsGoingInBatchesUntilNothingIsLeft(): void
    {
        $firstBatch = array_map(static fn (): string => Uuid::randomHex(), range(1, 500));
        $secondBatch = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];
        $batches = [$firstBatch, $secondBatch];
        $queries = [];
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturnCallback(
            static function (string $query) use (&$batches, &$queries): array {
                $queries[] = $query;

                return array_shift($batches) ?? [];
            },
        );
        $jobFailureHandler = $this->createMock(JobFailureHandler::class);
        $jobFailureHandler->expects($this->exactly(503))->method('fail')->willReturn(true);
        $service = new JobRecoveryService(
            $connection,
            $jobFailureHandler,
            $this->createMock(QueuedJobIdProvider::class),
            $this->createMock(LoggerInterface::class),
        );

        self::assertSame(503, $service->failUnfinishedJobs('Plugin deactivated'));
        self::assertCount(2, $queries);
        self::assertStringContainsString('LIMIT 500', $queries[0]);
    }

    public function testFailUnfinishedJobsStopsWhenNothingCanBeFailedAnymore(): void
    {
        $batch = array_map(static fn (): string => Uuid::randomHex(), range(1, 500));
        $calls = 0;
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturnCallback(
            static function () use (&$calls, $batch): array {
                ++$calls;

                return $batch;
            },
        );
        $jobFailureHandler = $this->createMock(JobFailureHandler::class);
        $jobFailureHandler->method('fail')->willReturn(false);
        $service = new JobRecoveryService(
            $connection,
            $jobFailureHandler,
            $this->createMock(QueuedJobIdProvider::class),
            $this->createMock(LoggerInterface::class),
        );

        self::assertSame(0, $service->failUnfinishedJobs('Plugin deactivated'));
        self::assertSame(1, $calls);
    }

    public function testFailUnfinishedJobsPassesTheReasonOn(): void
    {
        $jobId = Uuid::randomHex();
        $failures = $this->recordFailures($jobFailureHandler);
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturnOnConsecutiveCalls([$jobId], []);
        $service = new JobRecoveryService(
            $connection,
            $jobFailureHandler,
            $this->createMock(QueuedJobIdProvider::class),
            $this->createMock(LoggerInterface::class),
        );

        self::assertSame(1, $service->failUnfinishedJobs('Plugin deactivated'));
        self::assertSame([[$jobId, 'Plugin deactivated']], $failures());
    }

    public function testFailUnfinishedJobsNeverThrowsWhenTheCleanupFails(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willThrowException(new RuntimeException('Connection lost'));
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
     * @param list<string> $children
     * @param list<string> $parents
     * @param array<string, true> $queuedJobIds
     */
    private function createService(
        JobFailureHandler $jobFailureHandler,
        array $children = [],
        array $parents = [],
        array $queuedJobIds = [],
        ?QueuedJobIdProvider $queuedJobIdProvider = null,
        ?LoggerInterface $logger = null,
    ): JobRecoveryService {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturnCallback(
            static fn (string $query, array $params): array => match (true) {
                isset($params['createdBefore']) => $children,
                isset($params['inactiveBefore']) => $parents,
                default => [],
            },
        );
        if ($queuedJobIdProvider === null) {
            $queuedJobIdProvider = $this->createMock(QueuedJobIdProvider::class);
            $queuedJobIdProvider->method('isAvailable')->willReturn(true);
            $queuedJobIdProvider->method('getJobIds')->willReturn($queuedJobIds);
        }

        return new JobRecoveryService(
            $connection,
            $jobFailureHandler,
            $queuedJobIdProvider,
            $logger ?? $this->createMock(LoggerInterface::class),
        );
    }

    /**
     * @return callable(): list<array{string, string}>
     */
    private function recordFailures(?JobFailureHandler &$jobFailureHandler): callable
    {
        $failures = [];
        $jobFailureHandler = $this->createMock(JobFailureHandler::class);
        $jobFailureHandler->method('fail')->willReturnCallback(
            static function (string $jobId, string $reason) use (&$failures): bool {
                $failures[] = [$jobId, $reason];

                return true;
            },
        );

        return static function () use (&$failures): array {
            return $failures;
        };
    }
}
