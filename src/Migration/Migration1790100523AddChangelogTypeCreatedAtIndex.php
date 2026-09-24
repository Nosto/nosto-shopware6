<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1790100523AddChangelogTypeCreatedAtIndex extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1790100523;
    }

    /**
     * The changelog sync reads the oldest pending rows of one entity type. Without an index whose
     * order matches that, MySQL resolves the query with a filesort over every row of that type.
     *
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $schemaManager = $connection->createSchemaManager();

        if (!$schemaManager->tableExists('nosto_integration_entity_changelog')) {
            return;
        }

        $indexes = $schemaManager->listTableIndexes('nosto_integration_entity_changelog');
        if (array_key_exists('idx_nosto_entity_changelog_type_created', $indexes)) {
            return;
        }

        $sql = 'ALTER TABLE `nosto_integration_entity_changelog` ' .
            'ADD INDEX `idx_nosto_entity_changelog_type_created` (`entity_type`, `created_at`) ' .
            'LOCK=NONE';

        try {
            $connection->executeStatement($sql);
        } catch (Exception) {
            $connection->executeStatement(
                'CREATE INDEX `idx_nosto_entity_changelog_type_created` ' .
                'ON `nosto_integration_entity_changelog` (`entity_type`, `created_at`)',
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
