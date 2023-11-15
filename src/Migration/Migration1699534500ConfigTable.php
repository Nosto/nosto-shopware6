<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Migration;

use Doctrine\DBAL\Connection;
use Exception;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1699534500ConfigTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1699534500;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
            CREATE TABLE IF NOT EXISTS `nosto_integration_config` (
                `id` binary(16) NOT NULL,
                `configuration_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `configuration_value` json NOT NULL,
                `sales_channel_id` binary(16),
                `language_id` binary(16),
                `created_at` datetime(3) NOT NULL,
                `updated_at` datetime(3) DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.nosto_integration_config.key` (`configuration_key`,`sales_channel_id`,`language_id`),
                FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL;

        $connection->executeStatement($sql);

        $this->insertPreviousConfigurationIfExists($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function insertPreviousConfigurationIfExists(Connection $connection): void
    {
        $sql = <<<SQL
            SELECT
                LOWER(HEX(`id`)) AS `id`,
                configuration_key, 
                configuration_value,
                LOWER(HEX(`sales_channel_id`)) AS `sales_channel_id`,
                created_at,
                updated_at
            FROM `system_config`
            WHERE `configuration_key` LIKE '%NostoIntegration.config%'
        SQL;

        $configs = $connection->fetchAllAssociative($sql);

        foreach ($configs as $config) {
            $salesChannelId = $config['sales_channel_id'];
            $languageId = null;

            if ($salesChannelId) {
                $sql = 'SELECT LOWER(HEX(`language_id`)) AS `language_id` FROM `sales_channel` WHERE `id` = UNHEX(?)';
                $languageId = $connection->fetchOne($sql, [$salesChannelId]);
            }

            $data = $config;
            $data['id'] = Uuid::fromHexToBytes($config['id']);
            $data['configuration_key'] = str_replace('NostoIntegration.config.', '', $config['configuration_key']);
            $data['language_id'] = $languageId ? Uuid::fromHexToBytes($languageId) : null;
            $data['sales_channel_id'] = $salesChannelId ? Uuid::fromHexToBytes($salesChannelId) : null;

            try {
                $connection->insert('nosto_integration_config', $data);
            } catch (Exception $exception) {
                // Do nothing here as configuration already exists
            }
        }
    }

    private function getDefaultSalesChannelId(Connection $connection)
    {
        $sql = 'SELECT LOWER(HEX(`id`)) AS `id` FROM `sales_channel` WHERE `type_id` = UNHEX(?) AND `active` = \'1\'';

        return $connection->fetchOne($sql, [Defaults::SALES_CHANNEL_TYPE_STOREFRONT]);
    }
}
