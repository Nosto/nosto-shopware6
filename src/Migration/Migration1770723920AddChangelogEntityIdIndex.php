<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1770723920AddChangelogEntityIdIndex extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1770723920;
    }

    public function update(Connection $connection): void
    {
        $table = 'nosto_integration_entity_changelog';
        $indexName = 'nosto_integration_entity_id_idx';

        $exists = $connection->fetchOne(
            'SELECT COUNT(1) FROM information_schema.statistics WHERE table_name = :table AND index_name = :index AND table_schema = DATABASE()',
            [
                'table' => $table,
                'index' => $indexName,
            ],
        );

        if ($exists) {
            return;
        }

        // Create the index if it does not exist
        $connection->executeStatement(
            'CREATE INDEX ' . $indexName . ' ON ' . $table . ' (entity_id)',
        );
    }
}
