<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Response\GraphQL\Filter;

use InvalidArgumentException;
use Nosto\NostoIntegration\Search\Response\GraphQL\Filter\Values\FilterValue;
use stdClass;

abstract class Filter
{
    private const FILTER_RANGE_MIN = 'min';

    private const FILTER_RANGE_MAX = 'max';

    /**
     * @param FilterValue[] $values
     */
    public function __construct(
        protected readonly string $id,
        protected readonly string $name,
        protected readonly string $field,
        protected array $values = [],
    ) {
    }

    /**
     * Builds a new filter instance. May return null for unsupported filter types. Throws an exception for unknown
     * filter types.
     */
    public static function getInstance(stdClass $filter): ?Filter
    {
        return match ($filter->type) {
            'stats' => static::handleRangeSliderFilter($filter),
            'terms' => static::handleLabelTextFilter($filter),
            default => throw new InvalidArgumentException('The submitted filter is unknown.'),
        };
    }

    public function addValue(FilterValue $filterValue): self
    {
        $this->values[] = $filterValue;

        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    private static function handleLabelTextFilter(stdClass $facet): LabelTextFilter
    {
        $filter = new LabelTextFilter($facet->id, $facet->name, $facet->field);

        foreach ($facet->data as $item) {
            $filter->addValue(new FilterValue($item->value, $item->value));
        }

        return $filter;
    }

    private static function handleRangeSliderFilter(stdClass $facet): RangeSliderFilter
    {
        return new RangeSliderFilter(
            $facet->id,
            $facet->name,
            $facet->field,
            $facet->min,
            $facet->max,
        );
    }
}
