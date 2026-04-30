<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Exception\TableDoesNotExist;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1769413546AddChangelogLookupIndex extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1769413546;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $schemaManager = $connection->createSchemaManager();

        try {
            $table = $schemaManager->introspectTableByUnquotedName('nosto_integration_entity_changelog');
        } catch (TableDoesNotExist) {
            return;
        }

        if ($table->hasIndex('idx_nosto_entity_changelog_lookup')) {
            return;
        }

        $sql = 'ALTER TABLE `nosto_integration_entity_changelog` ' .
            'ADD INDEX `idx_nosto_entity_changelog_lookup` (`entity_id`, `product_number`, `entity_type`) ' .
            'LOCK=NONE';

        try {
            $connection->executeStatement($sql);
        } catch (Exception) {
            $connection->executeStatement(
                'CREATE INDEX `idx_nosto_entity_changelog_lookup` ' .
                'ON `nosto_integration_entity_changelog` (`entity_id`, `product_number`, `entity_type`)',
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
