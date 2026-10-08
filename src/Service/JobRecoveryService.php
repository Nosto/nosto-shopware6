<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Nosto\Scheduler\Entity\Job\JobEntity;
use Nosto\Scheduler\Model\Job\JobFailureHandler;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Throwable;

class JobRecoveryService
{
    private const DISPATCH_GRACE_PERIOD = '-10 minutes';

    private const GENERATION_INACTIVITY_PERIOD = '-30 minutes';

    private const MAX_JOBS_PER_RUN = 500;

    private const JOB_TYPE_PREFIX = 'nosto-integration%';

    private const ORPHANED_CHILD_REASON = 'The queued message of this job is missing, so the job can no longer finish.';

    private const STUCK_PARENT_REASON = 'Creating the child jobs of this job stopped before it finished.';

    public function __construct(
        private readonly Connection $connection,
        private readonly JobFailureHandler $jobFailureHandler,
        private readonly QueuedJobIdProvider $queuedJobIdProvider,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function recoverOrphanedJobs(): int
    {
        if (!$this->queuedJobIdProvider->isAvailable()) {
            return 0;
        }

        try {
            return $this->failOrphanedJobs();
        } catch (Throwable $e) {
            $this->logger->warning(sprintf('Unable to recover orphaned Nosto jobs: %s', $e->getMessage()));

            return 0;
        }
    }

    public function failUnfinishedJobs(string $reason): int
    {
        try {
            $totalCount = 0;
            do {
                $jobIds = $this->findUnfinishedJobIds();
                $failedCount = $this->failJobs($jobIds, $reason);
                $totalCount += $failedCount;
            } while ($failedCount > 0 && count($jobIds) === self::MAX_JOBS_PER_RUN);

            return $totalCount;
        } catch (Throwable $e) {
            $this->logger->warning(sprintf('Unable to fail unfinished Nosto jobs: %s', $e->getMessage()));

            return 0;
        }
    }

    private function failOrphanedJobs(): int
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $failedCount = $this->failJobs(
            $this->findOrphanedChildJobIds($now->modify(self::DISPATCH_GRACE_PERIOD)),
            self::ORPHANED_CHILD_REASON,
        );
        $failedCount += $this->failJobs(
            $this->findStuckParentJobIds($now->modify(self::GENERATION_INACTIVITY_PERIOD)),
            self::STUCK_PARENT_REASON,
        );

        if ($failedCount > 0) {
            $this->logger->warning(sprintf('Marked %d orphaned Nosto jobs as failed.', $failedCount));
        }

        return $failedCount;
    }

    /**
     * @param list<string> $jobIds
     */
    private function failJobs(array $jobIds, string $reason): int
    {
        $failedCount = 0;
        foreach (array_slice($jobIds, 0, self::MAX_JOBS_PER_RUN) as $jobId) {
            if ($this->jobFailureHandler->fail($jobId, $reason)) {
                ++$failedCount;
            }
        }

        return $failedCount;
    }

    /**
     * @return list<string>
     */
    private function findOrphanedChildJobIds(DateTimeImmutable $createdBefore): array
    {
        $childJobIds = $this->fetchJobIds(
            'SELECT LOWER(HEX(`id`)) FROM `nosto_scheduler_job` '
            . 'WHERE `status` IN (:statuses) AND `type` LIKE :typePrefix AND `parent_id` IS NOT NULL '
            . 'AND `created_at` <= :createdBefore ORDER BY `created_at`',
            [
                'createdBefore' => $createdBefore->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
        );
        if ($childJobIds === []) {
            return [];
        }

        $queuedJobIds = $this->queuedJobIdProvider->getJobIds();

        return array_values(array_filter(
            $childJobIds,
            static fn (string $jobId): bool => !isset($queuedJobIds[$jobId]),
        ));
    }

    /**
     * @return list<string>
     */
    private function findStuckParentJobIds(DateTimeImmutable $inactiveBefore): array
    {
        return $this->fetchJobIds(
            'SELECT LOWER(HEX(parent.`id`)) FROM `nosto_scheduler_job` parent '
            . 'WHERE parent.`status` IN (:statuses) AND parent.`type` LIKE :typePrefix '
            . 'AND parent.`parent_id` IS NULL AND parent.`child_generation_completed` = 0 '
            . 'AND COALESCE('
            . '(SELECT MAX(child.`created_at`) FROM `nosto_scheduler_job` child WHERE child.`parent_id` = parent.`id`), '
            . 'parent.`created_at`) <= :inactiveBefore '
            . 'AND NOT EXISTS (SELECT 1 FROM `nosto_scheduler_job` child '
            . 'WHERE child.`parent_id` = parent.`id` AND child.`status` IN (:statuses)) '
            . 'ORDER BY parent.`created_at`',
            [
                'inactiveBefore' => $inactiveBefore->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
        );
    }

    /**
     * @return list<string>
     */
    private function findUnfinishedJobIds(): array
    {
        return $this->fetchJobIds(
            'SELECT LOWER(HEX(`id`)) FROM `nosto_scheduler_job` '
            . 'WHERE `status` IN (:statuses) AND `type` LIKE :typePrefix '
            . 'ORDER BY (`parent_id` IS NULL), `created_at` LIMIT ' . self::MAX_JOBS_PER_RUN,
        );
    }

    /**
     * @param array<string, string> $params
     *
     * @return list<string>
     */
    private function fetchJobIds(string $query, array $params = []): array
    {
        return array_map(
            'strval',
            $this->connection->fetchFirstColumn(
                $query,
                $params + [
                    'statuses' => [JobEntity::TYPE_PENDING, JobEntity::TYPE_RUNNING],
                    'typePrefix' => self::JOB_TYPE_PREFIX,
                ],
                [
                    'statuses' => ArrayParameterType::STRING,
                ],
            ),
        );
    }
}
