<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Converts the legacy boolean `customerDataToNosto` setting into the new select mode.
 *
 * Existing explicit values are preserved: true (send) becomes "always", false (do not send)
 * becomes "never". Configs that were never set keep no row and resolve to the new
 * "rely-on-cookie" default at read time (see CustomerDataMode::fromConfigValue).
 *
 * @internal
 */
class Migration1769500001ConvertCustomerDataToNostoToMode extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1769500001;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(Connection $connection): void
    {
        if (!$connection->createSchemaManager()->tablesExist(['nosto_integration_config'])) {
            return;
        }

        $connection->executeStatement(
            <<<SQL
            UPDATE `nosto_integration_config`
            SET `configuration_value` = JSON_OBJECT('_value', 'always')
            WHERE `configuration_key` = 'customerDataToNosto'
              AND JSON_UNQUOTE(JSON_EXTRACT(`configuration_value`, '$._value')) IN ('true', '1')
            SQL,
        );

        $connection->executeStatement(
            <<<SQL
            UPDATE `nosto_integration_config`
            SET `configuration_value` = JSON_OBJECT('_value', 'never')
            WHERE `configuration_key` = 'customerDataToNosto'
              AND JSON_UNQUOTE(JSON_EXTRACT(`configuration_value`, '$._value')) IN ('false', '0')
            SQL,
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
