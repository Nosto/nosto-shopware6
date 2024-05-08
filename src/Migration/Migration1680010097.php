<?php

declare(strict_types=1);

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
        $this->addColumn($connection, 'od_nosto_entity_changelog', 'product_number', 'VARCHAR(64)');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
