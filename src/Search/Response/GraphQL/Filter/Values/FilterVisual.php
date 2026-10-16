<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Response\GraphQL\Filter\Values;

use Shopware\Core\Framework\Struct\Struct;

class FilterVisual extends Struct
{
    public function __construct(
        private readonly ?string $type = null,
        private readonly ?string $value = null,
    ) {
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }
}
