<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Migration;

use Doctrine\DBAL\Connection;
use Throwable;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1769413546AddChangelogLookupIndex extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1769413546;
    }

    public function update(Connection $connection): void
    {
        $schemaManager = $connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['nosto_integration_entity_changelog'])) {
            return;
        }

        $indexes = $schemaManager->listTableIndexes('nosto_integration_entity_changelog');
        if (array_key_exists('idx_nosto_entity_changelog_lookup', $indexes)) {
            return;
        }

        $sql = 'ALTER TABLE `nosto_integration_entity_changelog` ' .
            'ADD INDEX `idx_nosto_entity_changelog_lookup` (`entity_id`, `product_number`, `entity_type`) ' .
            'LOCK=NONE';

        try {
            $connection->executeStatement($sql);
        } catch (Throwable) {
            $connection->executeStatement(
                'CREATE INDEX `idx_nosto_entity_changelog_lookup` ' .
                'ON `nosto_integration_entity_changelog` (`entity_id`, `product_number`, `entity_type`)'
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
