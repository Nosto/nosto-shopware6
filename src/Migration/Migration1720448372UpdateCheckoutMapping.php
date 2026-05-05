<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1720448372UpdateCheckoutMapping extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1720448372;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(Connection $connection): void
    {
        $schemaManager = $connection->createSchemaManager();
        if (!$schemaManager->tableExists('nosto_integration_checkout_mapping')) {
            return;
        }

        $indexes = $schemaManager->listTableIndexes('nosto_integration_checkout_mapping');
        $alterParts = [
            'MODIFY COLUMN `reference` VARCHAR(50)',
            'MODIFY COLUMN `mapping_table` VARCHAR(25)',
        ];

        if (!array_key_exists('nosto_integration_cm_reference_idx', $indexes)) {
            $alterParts[] = 'ADD INDEX `nosto_integration_cm_reference_idx` (`reference`)';
        }

        if (!array_key_exists('nosto_integration_cm_mapping_table_idx', $indexes)) {
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
