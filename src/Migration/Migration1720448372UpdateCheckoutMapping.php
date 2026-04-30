<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Exception\TableDoesNotExist;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1720448372UpdateCheckoutMapping extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1720448372;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $schemaManager = $connection->createSchemaManager();
        try {
            $table = $schemaManager->introspectTableByUnquotedName('nosto_integration_checkout_mapping');
        } catch (TableDoesNotExist) {
            return;
        }

        $alterParts = [
            'MODIFY COLUMN `reference` VARCHAR(50)',
            'MODIFY COLUMN `mapping_table` VARCHAR(25)',
        ];

        if (!$table->hasIndex('nosto_integration_cm_reference_idx')) {
            $alterParts[] = 'ADD INDEX `nosto_integration_cm_reference_idx` (`reference`)';
        }

        if (!$table->hasIndex('nosto_integration_cm_mapping_table_idx')) {
            $alterParts[] = 'ADD INDEX `nosto_integration_cm_mapping_table_idx` (`mapping_table`)';
        }

        $connection->executeStatement(
            'ALTER TABLE `nosto_integration_checkout_mapping` ' . implode(', ', $alterParts),
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
