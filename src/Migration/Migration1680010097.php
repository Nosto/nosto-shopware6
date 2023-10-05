<?php declare(strict_types=1);

namespace Nosto\NostoIntegration\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1680010097 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1680010097;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection);
    }

    private function addColumn(Connection $connection): void
    {
        $table = 'od_nosto_entity_changelog';
        $column = 'product_number';

        if (!$this->hasColumn($table, $column, $connection)) {
            $connection->executeStatement('ALTER TABLE `'. $table .'` ADD COLUMN `'. $column .'` VARCHAR(64) NULL');
        }
    }

    private function hasColumn(string $table, string $columnName, Connection $connection): bool
    {
        return \in_array($columnName, array_column($connection->fetchAllAssociative(\sprintf('SHOW COLUMNS FROM `%s`', $table)), 'Field'), true);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
