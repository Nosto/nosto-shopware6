<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1769413547CreateFilterPayloadTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1769413547;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
            CREATE TABLE IF NOT EXISTS `nosto_filter_payload` (
                `id` binary(16) NOT NULL,
                `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
                `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
                `expires_at` datetime(3) NOT NULL,
                `created_at` datetime(3) NOT NULL,
                `updated_at` datetime(3) DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.nosto_filter_payload.token` (`token`),
                KEY `idx.nosto_filter_payload.expires_at` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL,
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
