<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class Migration1748349858AddEnableTaggingForAllSkusConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1748349858;
    }

    public function update(Connection $connection): void
    {
        $connection->insert('nosto_integration_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => 'enableTaggingForAllSkus',
            'configuration_value' => '{"_value": true}',
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
