<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Response\GraphQL\Filter;

use InvalidArgumentException;
use Nosto\NostoIntegration\Search\Response\GraphQL\Filter\Values\FilterValue;
use Nosto\Result\Graphql\Search\SearchResult\Products\Facet;
use Nosto\Result\Graphql\Search\SearchResult\Products\StatsFacet;
use Nosto\Result\Graphql\Search\SearchResult\Products\TermsFacet;

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
    public static function getInstance(Facet $facet): ?Filter
    {
        return match (true) {
            $facet instanceof StatsFacet => static::handleRangeSliderFilter($facet),
            $facet instanceof TermsFacet => static::handleLabelTextFilter($facet),
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

    private static function handleLabelTextFilter(TermsFacet $facet): LabelTextFilter
    {
        $filter = new LabelTextFilter($facet->getId(), $facet->getName(), $facet->getField());

        foreach ($facet->getData() as $item) {
            $filter->addValue(new FilterValue($item->getValue(), $item->getValue()));
        }

        return $filter;
    }

    private static function handleRangeSliderFilter(StatsFacet $facet): RangeSliderFilter
    {
        return new RangeSliderFilter(
            $facet->getId(),
            $facet->getName(),
            $facet->getField(),
            $facet->getMin(),
            $facet->getMax(),
        );
    }
}
