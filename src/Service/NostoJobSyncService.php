<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service;

use Doctrine\DBAL\Connection;
use Exception;
use Nosto\Scheduler\Entity\Job\JobEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class NostoJobSyncService
{
    public function __construct(
        private readonly EntityRepository $jobRepository,
        private readonly EntityRepository $jobMessageRepository,
        private readonly Connection $connection,
    ) {
    }

    public function deleteRunningFullProductSyncJobs(Context $context, string $handlerCode): void
    {
        $jobIds = $this->getAllJobIds($context, $handlerCode);
        if (empty($jobIds)) {
            throw new Exception(
                'There are currently no jobs available for cancellation, all jobs are completed successfully.',
            );
        }

        try {
            $this->connection->executeStatement(
                "DELETE FROM `messenger_messages` WHERE body LIKE '%nosto-integration%'",
            );

            $deleteIds = array_map(fn ($id) => [
                'id' => $id,
            ], array_values($jobIds));
            $this->jobMessageRepository->delete($deleteIds, $context);
            $this->jobRepository->delete($deleteIds, $context);
        } catch (Exception $e) {
            throw new Exception('An error occurred while cancelling full product sync jobs');
        }
    }

    /**
     * @return array<string>
     */
    private function getAllJobIds(Context $context, string $handlerCode): array
    {
        // 1. Find parent jobs: parent_id IS NULL AND status IN ('running', 'pending')
        $parentCriteria = new Criteria();
        $parentCriteria->addFilter(
            new AndFilter([
                new EqualsFilter('type', $handlerCode),
                new EqualsFilter('parentId', null),
                new EqualsAnyFilter('status', [JobEntity::TYPE_PENDING, JobEntity::TYPE_RUNNING]),
            ]),
        );
        $parentJobs = $this->jobRepository->search($parentCriteria, $context)->getEntities();
        $parentJobIds = array_map(fn ($job) => $job->getId(), $parentJobs->getElements());

        // 2. Find child jobs: parent_id IN (parentJobIds)
        $childJobIds = [];
        if (!empty($parentJobIds)) {
            $childCriteria = new Criteria();
            $childCriteria->addFilter(
                new AndFilter([
                    new EqualsFilter('type', $handlerCode),
                    new EqualsAnyFilter('parentId', $parentJobIds),
                ]),
            );
            $childJobs = $this->jobRepository->search($childCriteria, $context)->getEntities();
            $childJobIds = array_map(fn ($job) => $job->getId(), $childJobs->getElements());
        }

        // 3. Return union of parent and child job IDs
        return array_merge($parentJobIds, $childJobIds);
    }
}
