<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1699534600RemoveEnableSearchImpressionsConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1699534600;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
            DELETE FROM `nosto_integration_config`
            WHERE `configuration_key` = 'enableSearchImpressions'
        SQL;

        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
