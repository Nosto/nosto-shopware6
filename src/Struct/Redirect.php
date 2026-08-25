<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Struct;

use Shopware\Core\Framework\Struct\Struct;

class Redirect extends Struct
{
    public function __construct(
        protected readonly string $link,
        protected readonly bool $permanent = true,
    ) {
    }

    public function getLink(): string
    {
        return $this->link;
    }

    public function isPermanent(): bool
    {
        return $this->permanent;
    }
}
