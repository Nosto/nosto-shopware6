<?php

namespace Nosto\NostoIntegration\Struct;

use Shopware\Core\Framework\Struct\Struct;

class NostoService extends Struct
{
    private bool $enabled = false;

    public function enable(): bool
    {
        $this->enabled = true;

        return $this->enabled;
    }

    public function disable(): bool
    {
        $this->enabled = false;

        return $this->enabled;
    }

    public function getEnabled(): bool
    {
        return $this->enabled;
    }
}
