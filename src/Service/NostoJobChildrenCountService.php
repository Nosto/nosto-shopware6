<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;

class NostoJobChildrenCountService
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<int, array{
     *     parentJobId: string,
     *     childJobs: array{
     *         total: int,
     *         byStatus: array{
     *             success: int,
     *             pending: int,
     *             error: int
     *         }
     *     }
     * }>
     */
    public function getChildCount(array $parentJobIds): array
    {
        if ($parentJobIds === []) {
            return [];
        }

        $normalizedParentJobIds = array_values(array_filter($parentJobIds, static function ($id): bool {
            return is_string($id) && Uuid::isValid($id);
        }));

        if ($normalizedParentJobIds === []) {
            return [];
        }

        $parentBytes = array_map(
            static fn (string $id): string => Uuid::fromHexToBytes($id),
            $normalizedParentJobIds,
        );

        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(parent_id)) AS parentId,
                    COUNT(*) AS childCount,
                    SUM(CASE WHEN status IN (\'success\', \'succeed\', \'finished\', \'done\', \'completed\') THEN 1 ELSE 0 END) AS successCount,
                    SUM(CASE WHEN status IN (\'error\', \'failed\') THEN 1 ELSE 0 END) AS errorCount
             FROM nosto_scheduler_job
             WHERE parent_id IN (:parentIds)
             GROUP BY parent_id',
            [
                'parentIds' => $parentBytes,
            ],
            [
                'parentIds' => ArrayParameterType::BINARY,
            ],
        );

        $data = [];
        foreach ($rows as $row) {
            if (!isset($row['parentId'], $row['childCount'])) {
                continue;
            }

            $totalCount = (int) $row['childCount'];
            $successCount = isset($row['successCount']) ? (int) $row['successCount'] : 0;
            $errorCount = isset($row['errorCount']) ? (int) $row['errorCount'] : 0;
            $pendingCount = max(0, $totalCount - $successCount - $errorCount);

            $data[] = [
                'parentJobId' => (string) $row['parentId'],
                'childJobs' => [
                    'total' => $totalCount,
                    'byStatus' => [
                        'success' => $successCount,
                        'pending' => $pendingCount,
                        'error' => $errorCount,
                    ],
                ],
            ];
        }

        return $data;
    }
}
