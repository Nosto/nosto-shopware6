<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Migration;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1699264539 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1699264539;
    }

    /*
     * Removing the old configuration setting
     */
    public function update(Connection $connection): void
    {
        $qb = $connection
            ->createQueryBuilder()
            ->where('configuration_key IN (:keys)')
            ->setParameter('keys', ['overdose_nosto.config.hidden.dailySyncLastTime'], ArrayParameterType::STRING);

        $qb->andWhere('sales_channel_id IS NULL');

        $qb->delete('system_config')->executeStatement();
    }

    public function updateDestructive(Connection $connection): void
    {
        // Nothing to do here
    }
}
