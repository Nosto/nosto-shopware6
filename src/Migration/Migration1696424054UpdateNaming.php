<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1696424054UpdateNaming extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1696424054;
    }

    public function update(Connection $connection): void
    {
        $this->updateChangeLogEntityTable($connection);
        $this->updateCheckoutMappingTable($connection);
    }

    private function updateChangeLogEntityTable(Connection $connection): void
    {
        $sqlIndexesRename = <<<SQL
            ALTER TABLE od_nosto_entity_changelog
              DROP INDEX od_entity_type_idx,
              DROP INDEX od_created_at_idx,
              ADD INDEX nosto_integration_entity_type_idx (entity_type),
              ADD INDEX nosto_integration_created_at_idx (created_at);
        SQL;

        $sqlTableRename = <<<SQL
            RENAME TABLE od_nosto_entity_changelog TO nosto_integration_entity_changelog;
        SQL;

        $connection->executeStatement($sqlIndexesRename);
        $connection->executeStatement($sqlTableRename);
    }

    private function updateCheckoutMappingTable(Connection $connection): void
    {
        $sqlTableRename = <<<SQL
            RENAME TABLE nosto_checkout_mapping TO nosto_integration_checkout_mapping;
        SQL;

        $connection->executeStatement($sqlTableRename);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
