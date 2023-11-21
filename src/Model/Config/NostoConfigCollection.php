<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Config;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                   add(NostoConfigEntity $entity)
 * @method void                   set(string $key, NostoConfigEntity $entity)
 * @method NostoConfigEntity[]    getIterator()
 * @method NostoConfigEntity[]    getElements()
 * @method NostoConfigEntity|null get(string $key)
 * @method NostoConfigEntity|null first()
 * @method NostoConfigEntity|null last()
 */
class NostoConfigCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'nosto_integration_config_collection';
    }

    protected function getExpectedClass(): string
    {
        return NostoConfigEntity::class;
    }
}
