<?php declare(strict_types=1);

namespace Nosto\NostoIntegration\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1642772960Changelog extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1642772960;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS `od_nosto_entity_changelog` (
            `id`            BINARY(16)      NOT NULL,
            `entity_type`   VARCHAR(255)    NOT NULL,
            `entity_id`     BINARY(16)      NOT NULL,
            `created_at`    DATETIME(3)     NOT NULL,
            `updated_at`    DATETIME(3)     NULL,
            INDEX `od_entity_type_idx` (`entity_type`),
            INDEX `od_created_at_idx` (`created_at`),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
