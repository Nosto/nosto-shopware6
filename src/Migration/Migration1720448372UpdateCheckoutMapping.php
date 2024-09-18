<?php declare(strict_types=1);

namespace Nosto\NostoIntegration\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1720448372UpdateCheckoutMapping extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1720448372;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
            ALTER TABLE nosto_integration_checkout_mapping
              ADD INDEX nosto_integration_cm_reference_idx (reference),
              ADD INDEX nosto_integration_cm_mapping_table_idx (mapping_table),
              MODIFY COLUMN reference VARCHAR(50),
              MODIFY COLUMN mapping_table VARCHAR(25);
        SQL;

        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
