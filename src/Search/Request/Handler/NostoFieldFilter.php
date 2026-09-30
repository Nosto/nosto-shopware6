<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler;

/**
 * A single filter decoded from a `products.filter.<field>` query parameter.
 *
 * Either $values is non-empty (a terms filter) or $ranges is non-empty (a stats filter),
 * never both. Each range is a map with any of the keys 'gt', 'gte', 'lt', 'lte'.
 */
final class NostoFieldFilter
{
    /**
     * @param string[] $values
     * @param array<int, array<string, string>> $ranges
     */
    public function __construct(
        public readonly string $field,
        public readonly array $values = [],
        public readonly array $ranges = [],
    ) {
    }

    public function isRange(): bool
    {
        return $this->ranges !== [];
    }
}
