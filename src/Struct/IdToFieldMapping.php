<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Struct;

use Shopware\Core\Framework\Struct\Struct;

class IdToFieldMapping extends Struct
{
    /**
     * @param array<string, string> $map
     */
    public function __construct(
        private array $map = [],
    ) {
    }

    public function addMapping(string $id, string $field): void
    {
        $this->map[$id] = $field;
    }

    public function getMapping(string $id): ?string
    {
        return $this->map[$id] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function getMap(): array
    {
        return $this->map;
    }
}
