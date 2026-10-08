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

    private const MAX_JOBS_PER_RUN = 500;

    private const JOB_TYPE_PREFIX = 'nosto-integration%';

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
            return $this->failJobs($this->findUnfinishedJobs(), $reason);
        } catch (Throwable $e) {
            $this->logger->warning(sprintf('Unable to fail unfinished Nosto jobs: %s', $e->getMessage()));

            return 0;
        }
    }

    private function failOrphanedJobs(): int
    {
        $createdBefore = new DateTimeImmutable(self::DISPATCH_GRACE_PERIOD, new DateTimeZone('UTC'));
        $jobs = $this->findUnfinishedJobs($createdBefore);
        if ($jobs === []) {
            return 0;
        }

        $queuedJobIds = $this->queuedJobIdProvider->getJobIds();
        $orphanedJobs = array_filter(
            $jobs,
            static fn (array $job): bool => !$job['generation_completed'] && !isset($queuedJobIds[$job['id']]),
        );
        $failedCount = $this->failJobs(
            $orphanedJobs,
            'The queued message of this job is missing, so the job can no longer finish.',
        );

        if ($failedCount > 0) {
            $this->logger->warning(sprintf('Marked %d orphaned Nosto jobs as failed.', $failedCount));
        }

        return $failedCount;
    }

    /**
     * @param list<array{id: string, has_parent: bool, generation_completed: bool}> $jobs
     */
    private function failJobs(array $jobs, string $reason): int
    {
        $jobs = array_slice(array_values($jobs), 0, self::MAX_JOBS_PER_RUN);
        usort($jobs, static fn (array $a, array $b): int => $b['has_parent'] <=> $a['has_parent']);

        $failedCount = 0;
        foreach ($jobs as $job) {
            if ($this->jobFailureHandler->fail($job['id'], $reason)) {
                ++$failedCount;
            }
        }

        return $failedCount;
    }

    /**
     * @return list<array{id: string, has_parent: bool, generation_completed: bool}>
     */
    private function findUnfinishedJobs(?DateTimeImmutable $createdBefore = null): array
    {
        $query = 'SELECT LOWER(HEX(`id`)) AS `id`, `parent_id` IS NOT NULL AS `has_parent`, '
            . '`child_generation_completed` AS `generation_completed` '
            . 'FROM `nosto_scheduler_job` WHERE `status` IN (:statuses) AND `type` LIKE :typePrefix';
        $params = [
            'statuses' => [JobEntity::TYPE_PENDING, JobEntity::TYPE_RUNNING],
            'typePrefix' => self::JOB_TYPE_PREFIX,
        ];
        $types = [
            'statuses' => ArrayParameterType::STRING,
        ];
        if ($createdBefore !== null) {
            $query .= ' AND `created_at` <= :createdBefore';
            $params['createdBefore'] = $createdBefore->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        }
        $query .= ' ORDER BY `created_at`';

        return array_map(
            static fn (array $row): array => [
                'id' => (string) $row['id'],
                'has_parent' => (bool) $row['has_parent'],
                'generation_completed' => (bool) $row['generation_completed'],
            ],
            $this->connection->fetchAllAssociative($query, $params, $types),
        );
    }
}
