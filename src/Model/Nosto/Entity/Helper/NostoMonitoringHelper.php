<?php
declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Helper;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

class NostoMonitoringHelper
{
    private readonly Connection $connection;

    public function __construct(
        private readonly ContainerInterface $container
    ) {
        /** @var Connection $connection */
        $connection = $container->get(Connection::class);
        $this->connection = $connection;
    }

    /**
     * Clears Nosto scheduler jobs that are in 'running' or 'pending' status,
     * including their associated job messages. The function retrieves the
     * job IDs and deletes them from the database. If successful, it returns
     * a success message, otherwise, it returns an error message.
     *
     * @return array
     */
    public function clearNostoJobs(): array
    {
        $ret = [
            'status' => 'info',
            'message' => 'There are currently no jobs available for deletion, all jobs are completed successfully.'
        ];

        try {
            $jobQuery = '
                SELECT id 
                FROM `nosto_scheduler_job` 
                WHERE (`parent_id` IS NULL AND `status` IN (\'running\', \'pending\'))
                   OR `parent_id` IN (
                       SELECT id 
                       FROM `nosto_scheduler_job` 
                       WHERE `parent_id` IS NULL 
                         AND `status` IN (\'running\', \'pending\')
                   )
            ';

            $jobsToDelete = $this->connection->fetchAllAssociative($jobQuery);

            if (!empty($jobsToDelete)) {
                $jobIds = array_column($jobsToDelete, 'id');

                $deleteMessagesQuery = '
                    DELETE FROM `nosto_scheduler_job_message`
                    WHERE job_id IN (:jobIds)
                ';
                $this->connection->executeStatement(
                    $deleteMessagesQuery,
                    ['jobIds' => $jobIds],
                    ['jobIds' => ArrayParameterType::BINARY]
                );

                $deleteJobsQuery = '
                    DELETE FROM `nosto_scheduler_job`
                    WHERE id IN (:jobIds)
                ';
                $this->connection->executeStatement(
                    $deleteJobsQuery,
                    ['jobIds' => $jobIds],
                    ['jobIds' => ArrayParameterType::BINARY]
                );

                $ret['status'] = 'success';
                $ret['message'] = 'Jobs have been successfully cleared.';
            }

            return $ret;
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
}
