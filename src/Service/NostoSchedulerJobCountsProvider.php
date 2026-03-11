<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

class NostoSchedulerJobCountsProvider
{
    private const CHILD_SUCCESS_STATUSES = ['success', 'succeed', 'finished', 'done', 'completed'];

    private const CHILD_ERROR_STATUSES = ['error', 'failed'];

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<string, array<string, array<string, int>>>
     */
    public function getCountsByJobIds(array $jobIds): array
    {
        $normalizedJobIds = $this->normalizeJobIds($jobIds);

        if ($normalizedJobIds === []) {
            return [];
        }

        $binaryJobIds = $this->toBinaryIds($normalizedJobIds);
        if ($binaryJobIds === []) {
            return [];
        }

        $countsByJobId = [];
        foreach ($normalizedJobIds as $jobId) {
            $countsByJobId[$jobId] = self::createEmptyCounts();
        }

        foreach ($this->fetchChildRows($binaryJobIds) as $row) {
            if (!isset($row['parentId']) || !is_string($row['parentId']) || !isset($countsByJobId[$row['parentId']])) {
                continue;
            }

            $totalCount = (int) ($row['totalCount'] ?? 0);
            $successCount = (int) ($row['successCount'] ?? 0);
            $errorCount = (int) ($row['errorCount'] ?? 0);

            $countsByJobId[$row['parentId']]['childJobs'] = [
                'total' => $totalCount,
                'success' => $successCount,
                'pending' => max(0, $totalCount - $successCount - $errorCount),
                'error' => $errorCount,
            ];
        }

        foreach ($this->fetchMessageRows($binaryJobIds) as $row) {
            if (!isset($row['jobId']) || !is_string($row['jobId']) || !isset($countsByJobId[$row['jobId']])) {
                continue;
            }

            $countsByJobId[$row['jobId']]['messages'] = [
                'total' => (int) ($row['totalCount'] ?? 0),
                'info' => (int) ($row['infoCount'] ?? 0),
                'warning' => (int) ($row['warningCount'] ?? 0),
                'error' => (int) ($row['errorCount'] ?? 0),
            ];
        }

        return $countsByJobId;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchChildRows(array $jobIds): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(parent_id)) AS parentId,
                    COUNT(*) AS totalCount,
                    SUM(CASE WHEN status IN (:successStatuses) THEN 1 ELSE 0 END) AS successCount,
                    SUM(CASE WHEN status IN (:errorStatuses) THEN 1 ELSE 0 END) AS errorCount
             FROM nosto_scheduler_job
             WHERE parent_id IN (:parentIds)
             GROUP BY parent_id',
            [
                'parentIds' => $jobIds,
                'successStatuses' => self::CHILD_SUCCESS_STATUSES,
                'errorStatuses' => self::CHILD_ERROR_STATUSES,
            ],
            [
                'parentIds' => ArrayParameterType::BINARY,
                'successStatuses' => ArrayParameterType::STRING,
                'errorStatuses' => ArrayParameterType::STRING,
            ],
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchMessageRows(array $jobIds): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(job_id)) AS jobId,
                    COUNT(*) AS totalCount,
                    SUM(CASE WHEN type = \'info-message\' THEN 1 ELSE 0 END) AS infoCount,
                    SUM(CASE WHEN type = \'warning-message\' THEN 1 ELSE 0 END) AS warningCount,
                    SUM(CASE WHEN type = \'error-message\' THEN 1 ELSE 0 END) AS errorCount
             FROM nosto_scheduler_job_message
             WHERE job_id IN (:jobIds)
             GROUP BY job_id',
            [
                'jobIds' => $jobIds,
            ],
            [
                'jobIds' => ArrayParameterType::BINARY,
            ],
        );
    }

    /**
     * @return string[]
     */
    private function toBinaryIds(array $jobIds): array
    {
        $binaryIds = [];
        foreach ($jobIds as $jobId) {
            $bytes = hex2bin($jobId);
            if ($bytes !== false) {
                $binaryIds[] = $bytes;
            }
        }

        if ($binaryIds === []) {
            return [];
        }

        return $binaryIds;
    }

    /**
     * @return string[]
     */
    private function normalizeJobIds(array $jobIds): array
    {
        $normalizedIds = [];
        foreach ($jobIds as $id) {
            if (!is_string($id)) {
                continue;
            }

            $normalized = strtolower(trim($id));
            if (strlen($normalized) !== 32 || !ctype_xdigit($normalized)) {
                continue;
            }

            $normalizedIds[$normalized] = true;
        }

        return array_keys($normalizedIds);
    }

    /**
     * @return array<string, array<string, int>>
     */
    public static function createEmptyCounts(): array
    {
        return [
            'childJobs' => [
                'total' => 0,
                'success' => 0,
                'pending' => 0,
                'error' => 0,
            ],
            'messages' => [
                'total' => 0,
                'info' => 0,
                'warning' => 0,
                'error' => 0,
            ],
        ];
    }
}
