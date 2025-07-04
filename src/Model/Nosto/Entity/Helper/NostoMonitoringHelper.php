<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Helper;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\NostoIntegration;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ContainerInterface;

class NostoMonitoringHelper
{
    private readonly Connection $connection;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly LoggerInterface $logger,
        private readonly ConfigProvider $nostoConfigProvider,
        private readonly NostoIntegration $plugin,
    ) {
        /** @var Connection $connection */
        $connection = $container->get(Connection::class);
        $this->connection = $connection;
    }

    /**
     * Get the current version of Nosto Plugin
     *
     * @return string
     */
    public function getPluginVersion(): string
    {
        $composerPath = dirname($this->plugin->getPath()) . '/composer.json';
        if (!file_exists($composerPath)) {
            return 'composer.json not found';
        }

        $composer = json_decode(file_get_contents($composerPath), true);
        return $composer['version'] ?? 'N/A';
    }

    /**
     * Get the Nosto scheduler jobs information
     *
     * @return array
     */
    public function getSchedulerJobs(): array
    {
        // Get Nosto scheduler jobs information
        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT * FROM nosto_scheduler_job ORDER BY created_at DESC LIMIT 100'
            );

            return array_map(function (array $row) {
                // Convert binary fields to hex
                foreach (['id', 'parent_id'] as $field) {
                    if (isset($row[$field]) && !mb_check_encoding($row[$field], 'UTF-8')) {
                        $row[$field] = bin2hex($row[$field]);
                    }
                }

                // base64-encode large binary 'message' values
                if (isset($row['message']) && !mb_check_encoding($row['message'], 'UTF-8')) {
                    $row['message'] = base64_encode($row['message']);
                }

                return $row;
            }, $rows);
        } catch (Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Clears Nosto scheduler jobs that are in 'running' or 'pending' status,
     * including their associated job messages. The function retrieves the
     * job IDs and deletes them from the database. If successful, it returns
     * a success message, otherwise, it returns an error message.
     *
     * @return array<string, mixed>
     */
    public function clearNostoJobs(): array
    {
        $ret = [
            'status' => 'info',
            'message' => 'There are currently no jobs available for deletion, all jobs are completed successfully.',
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
                    [
                        'jobIds' => $jobIds,
                    ],
                    [
                        'jobIds' => ArrayParameterType::BINARY,
                    ],
                );

                $deleteJobsQuery = '
                    DELETE FROM `nosto_scheduler_job`
                    WHERE id IN (:jobIds)
                ';
                $this->connection->executeStatement(
                    $deleteJobsQuery,
                    [
                        'jobIds' => $jobIds,
                    ],
                    [
                        'jobIds' => ArrayParameterType::BINARY,
                    ],
                );

                $ret['status'] = 'success';
                $ret['message'] = 'Jobs have been successfully cleared.';
            }

            return $ret;
        } catch (\Exception $e) {
            $this->logger->error('Unable to clear Nosto jobs due to:' . $e->getMessage());

            return [
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get structured Nosto configuration as an array
     *
     * @return array<string, mixed> Configuration array with all Nosto settings
     * @throws Exception
     */
    public function getStructuredNostoConfiguration(?string $salesChannelId, ?string $languageId): array
    {
        $configuration = $this->getAllConfigsReflection($salesChannelId, $languageId);

        $configuration['domainForGeneratingProductUrl'] = null;
        if ($configuration['getDomainId']) {
            $configuration['domainForGeneratingProductUrl'] = $this->connection->fetchOne(
                'SELECT scd.url FROM sales_channel_domain as scd WHERE scd.id = :salesChannelId',
                [
                    'salesChannelId' => Uuid::fromHexToBytes($configuration['getDomainId']),
                ],
            );
        }

        return $configuration;
    }

    /**
     * Get Nosto configuration (dynamic & hybrid way of doing it)
     *
     * @return array<string, mixed>
     */
    public function getAllConfigsReflection(?string $salesChannelId, ?string $languageId): array
    {
        $reflection = new \ReflectionClass($this->nostoConfigProvider);
        $configs = [];

        // Manually handled methods (with special param needs)
        // TODO - extracted IDs - values needed
        $configs['tagFieldKeys'] = [
            'getTagFieldKey1' => $this->nostoConfigProvider->getTagFieldKey(1, $salesChannelId, $languageId),
            'getTagFieldKey2' => $this->nostoConfigProvider->getTagFieldKey(2, $salesChannelId, $languageId),
            'getTagFieldKey3' => $this->nostoConfigProvider->getTagFieldKey(3, $salesChannelId, $languageId),
        ];

        // Methods that should be excluded from dynamic call (because they were manually handled or incompatible)
        $excluded = [
            'getTagFieldKey',
        ];

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $methodName = $method->getName();

            // Skip excluded or already-handled methods
            if (
                in_array($methodName, $excluded, true) ||
                !(
                    str_starts_with($methodName, 'get') ||
                    str_starts_with($methodName, 'is') ||
                    str_starts_with($methodName, 'should')
                )
            ) {
                continue;
            }

            $params = $method->getParameters();
            if (count($params) !== 2) {
                $configs[$methodName] = 'SKIPPED: incompatible parameter count';
                continue;
            }

            try {
                $configs[$methodName] = $method->invoke($this->nostoConfigProvider, $salesChannelId, $languageId);
            } catch (\Throwable $e) {
                $configs[$methodName] = 'ERROR: ' . $e->getMessage();
            }
        }

        return $configs;
    }
}
