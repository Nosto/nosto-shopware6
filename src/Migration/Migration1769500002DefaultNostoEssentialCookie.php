<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * "Treat Nosto as an essential cookie" (ignoreCookieConsent) now defaults to ON so that
 * upgrading stores keep the previous behaviour where Nosto loaded for every visitor.
 *
 * Older installs may have this value explicitly stored as false (where it previously had no
 * effect, since Nosto was always registered as a required cookie). Normalise those to true on
 * upgrade so nobody is silently switched into the new strict, consent-gated loading mode.
 * Merchants can still opt into strict mode by turning the setting off after upgrading.
 *
 * @internal
 */
class Migration1769500002DefaultNostoEssentialCookie extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1769500002;
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
            SET `configuration_value` = JSON_OBJECT('_value', true)
            WHERE `configuration_key` = 'ignoreCookieConsent'
              AND JSON_EXTRACT(`configuration_value`, '$._value') = CAST('false' AS JSON)
            SQL,
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
