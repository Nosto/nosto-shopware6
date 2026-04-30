<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Exception\TableDoesNotExist;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1745505195RemoveEnableSearchImpressionsConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1745505195;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        try {
            $connection->createSchemaManager()->introspectTableByUnquotedName('nosto_integration_config');
        } catch (TableDoesNotExist) {
            return;
        }

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
