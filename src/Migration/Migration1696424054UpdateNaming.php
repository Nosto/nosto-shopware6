<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1696424054UpdateNaming extends MigrationStep
{
    private const LEGACY_CHANGELOG_TABLE = 'od_nosto_entity_changelog';

    private const CHANGELOG_TABLE = 'nosto_integration_entity_changelog';

    private const LEGACY_CHECKOUT_MAPPING_TABLE = 'nosto_checkout_mapping';

    private const CHECKOUT_MAPPING_TABLE = 'nosto_integration_checkout_mapping';

    public function getCreationTimestamp(): int
    {
        return 1696424054;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $this->updateChangeLogEntityTable($connection);
        $this->updateCheckoutMappingTable($connection);
    }

    /**
     * @throws Exception
     */
    private function updateChangeLogEntityTable(Connection $connection): void
    {
        $schemaManager = $connection->createSchemaManager();
        if (!$schemaManager->tableExists(self::LEGACY_CHANGELOG_TABLE)) {
            return;
        }

        if ($schemaManager->tableExists(self::CHANGELOG_TABLE)) {
            $connection->executeStatement('DROP TABLE IF EXISTS `' . self::LEGACY_CHANGELOG_TABLE . '`');

            return;
        }

        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `od_nosto_entity_changelog`
                  DROP INDEX `od_entity_type_idx`,
                  DROP INDEX `od_created_at_idx`,
                  ADD INDEX `nosto_integration_entity_type_idx` (`entity_type`),
                  ADD INDEX `nosto_integration_created_at_idx` (`created_at`)
            SQL,
        );

        $connection->executeStatement(
            'RENAME TABLE `od_nosto_entity_changelog` TO `nosto_integration_entity_changelog`',
        );
    }

    /**
     * @throws Exception
     */
    private function updateCheckoutMappingTable(Connection $connection): void
    {
        $schemaManager = $connection->createSchemaManager();
        if (!$schemaManager->tableExists(self::LEGACY_CHECKOUT_MAPPING_TABLE)) {
            return;
        }

        if ($schemaManager->tableExists(self::CHECKOUT_MAPPING_TABLE)) {
            $connection->executeStatement('DROP TABLE IF EXISTS `' . self::LEGACY_CHECKOUT_MAPPING_TABLE . '`');

            return;
        }

        $connection->executeStatement(
            'RENAME TABLE `nosto_checkout_mapping` TO `nosto_integration_checkout_mapping`',
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
