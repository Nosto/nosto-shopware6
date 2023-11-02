<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Struct;

use Nosto\NostoIntegration\Search\Response\GraphQL\Filter\Filter;
use Shopware\Core\Framework\Struct\Struct;

class FiltersExtension extends Struct
{
    /**
     * @param Filter[] $filters
     */
    public function __construct(
        private array $filters = []
    ) {
    }

    public function addFilter(Filter $filter): self
    {
        $this->filters[$filter->getId()] = $filter;

        return $this;
    }

    /**
     * @return Filter[]
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getFilter(string $id): ?Filter
    {
        if (!isset($this->filters[$id])) {
            return null;
        }

        return $this->filters[$id];
    }
}
