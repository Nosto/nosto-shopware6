<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Helper;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\NostoIntegration;
use Psr\Log\LoggerInterface;
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
     * Get serialized Nosto configuration as an array
     *
     * @return array<string, mixed> Configuration array with all Nosto settings
     */
    public function getSerializedNostoConfiguration(?string $salesChannelId, ?string $languageId): array
    {
        return [
            // Account Settings
            'accountEnabled' => $this->nostoConfigProvider->isAccountEnabled($salesChannelId, $languageId),
            'accountId' => $this->nostoConfigProvider->getAccountId($salesChannelId, $languageId),
            'accountName' => $this->nostoConfigProvider->getAccountName($salesChannelId, $languageId),

            // API Tokens
            'productToken' => $this->nostoConfigProvider->getProductToken($salesChannelId, $languageId),
            'emailToken' => $this->nostoConfigProvider->getEmailToken($salesChannelId, $languageId),
            'appToken' => $this->nostoConfigProvider->getAppToken($salesChannelId, $languageId),
            'searchToken' => $this->nostoConfigProvider->getSearchToken($salesChannelId, $languageId),

            // Personalized Search & Category Merchandising
            'enablePersonalizedSearch' => $this->nostoConfigProvider->isSearchEnabled($salesChannelId, $languageId),
            'enableCategoryMerchandising' => $this->nostoConfigProvider->isNavigationEnabled(
                $salesChannelId,
                $languageId,
            ),

            // General Settings
            'initializeNostoAfterInteraction' => $this->nostoConfigProvider->shouldInitializeNostoAfterInteraction(
                $salesChannelId,
                $languageId,
            ),
            'domainId' => $this->nostoConfigProvider->getDomainId($salesChannelId, $languageId),

            // Tags Assignment
            'selectedCustomFields' => $this->nostoConfigProvider->getSelectedCustomFields($salesChannelId, $languageId),
            'tag1' => $this->nostoConfigProvider->getTagFieldKey(1, $salesChannelId, $languageId),
            'tag2' => $this->nostoConfigProvider->getTagFieldKey(2, $salesChannelId, $languageId),
            'tag3' => $this->nostoConfigProvider->getTagFieldKey(3, $salesChannelId, $languageId),
            'googleCategory' => $this->nostoConfigProvider->getGoogleCategory($salesChannelId, $languageId),

            // Feature Flags (these return enum values)
            'productIdentifier' => $this->nostoConfigProvider->getProductIdentifier(
                $salesChannelId,
                $languageId,
            )->value,
            'ratingsReviews' => $this->nostoConfigProvider->getRatingReviews($salesChannelId, $languageId)->value,
            'stockField' => $this->nostoConfigProvider->getStockField($salesChannelId, $languageId)->value,
            'crossSellingSync' => $this->nostoConfigProvider->getCrossSellingSyncOption(
                $salesChannelId,
                $languageId,
            )->value,
            'categoryNaming' => $this->nostoConfigProvider->getCategoryNamingOption(
                $salesChannelId,
                $languageId,
            )->value,
            'categoryBlocklist' => $this->nostoConfigProvider->getCategoryBlocklist($salesChannelId, $languageId),

            // Toggle Features
            'enableVariations' => $this->nostoConfigProvider->isEnabledVariations($salesChannelId, $languageId),
            'enableProductProperties' => $this->nostoConfigProvider->isEnabledProductProperties(
                $salesChannelId,
                $languageId,
            ),
            'enableAlternateImages' => $this->nostoConfigProvider->isEnabledAlternateImages(
                $salesChannelId,
                $languageId,
            ),
            'enableInventoryLevels' => $this->nostoConfigProvider->isEnabledInventoryLevels(
                $salesChannelId,
                $languageId,
            ),
            'enableCustomerDataToNosto' => $this->nostoConfigProvider->isEnabledCustomerDataToNosto(
                $salesChannelId,
                $languageId,
            ),
            'enableSyncInactiveProducts' => $this->nostoConfigProvider->isEnabledSyncInactiveProducts(
                $salesChannelId,
                $languageId,
            ),
            'enableProductPublishedDateTagging' => $this->nostoConfigProvider->isEnabledProductPublishedDateTagging(
                $salesChannelId,
                $languageId,
            ),
            'enableReloadRecommendations' => $this->nostoConfigProvider->isEnabledReloadRecommendationsAfterAdding(
                $salesChannelId,
                $languageId,
            ),
            'enableProductLabellingSync' => $this->nostoConfigProvider->isEnabledProductLabellingSync(
                $salesChannelId,
                $languageId,
            ),
            'enableStoreAbandonedCartData' => $this->nostoConfigProvider->isEnabledStoreAbandonedCartData(
                $salesChannelId,
                $languageId,
            ),
            'enableIgnoreCookieConsent' => $this->nostoConfigProvider->isEnabledIgnoreCookieConsent(
                $salesChannelId,
                $languageId,
            ),
            'enableSyncFirstAvailableVariant' => $this->nostoConfigProvider->isEnabledSyncFirstAvailableVariant(
                $salesChannelId,
                $languageId,
            ),

            // daily Sync & Cleanup Settings
            'dailyProductSyncEnabled' => $this->nostoConfigProvider->isDailyProductSyncEnabled(
                $salesChannelId,
                $languageId,
            ),
            //            'dailyProductSyncTime' => $this->nostoConfigProvider->getDailyProductSyncTime($salesChannelId, $languageId),
            'oldJobCleanupEnabled' => $this->nostoConfigProvider->isOldJobCleanupEnabled($salesChannelId, $languageId),
            'oldJobCleanupPeriod' => $this->nostoConfigProvider->getOldJobCleanupPeriod($salesChannelId, $languageId),
            'oldNostoDataCleanupEnabled' => $this->nostoConfigProvider->isOldNostoDataCleanupEnabled(
                $salesChannelId,
                $languageId,
            ),
            'oldNostoDataCleanupPeriod' => $this->nostoConfigProvider->getOldNostoDataCleanupPeriod(
                $salesChannelId,
                $languageId,
            ),

            // Full config array for debugging/additional data
            'fullConfig' => $this->nostoConfigProvider->toArray($salesChannelId, $languageId),
        ];
    }
}
