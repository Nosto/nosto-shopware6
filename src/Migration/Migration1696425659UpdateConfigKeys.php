<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1696425659UpdateConfigKeys extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1696425659;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
            UPDATE system_config
            SET configuration_key = REPLACE(configuration_key, 'overdose_nosto', 'NostoIntegration')
            WHERE configuration_key LIKE 'overdose_nosto%';
        SQL;

        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
