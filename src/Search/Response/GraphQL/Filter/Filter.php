<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Response\GraphQL\Filter;

use InvalidArgumentException;
use Nosto\NostoIntegration\Search\Response\GraphQL\Filter\Values\FilterValue;

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
        protected array $values = [],
    ) {
    }

    /**
     * Builds a new filter instance. May return null for unsupported filter types. Throws an exception for unknown
     * filter types.
     */
    public static function getInstance($filter): ?Filter
    {
        switch ($filter['type']) {
            case 'stats':
                return static::handleRangeSliderFilter($filter);
            case 'terms':
                return static::handleLabelTextFilter($filter);
            default:
                throw new InvalidArgumentException('The submitted filter is unknown.');
        }
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

    public function getValues(): array
    {
        return $this->values;
    }

    private static function handleLabelTextFilter($filter): LabelTextFilter
    {
        $customFilter = new LabelTextFilter($filter->getName(), $filter->getDisplayName());

        foreach ($filter->getValues() as $item) {
            $customFilter->addValue(new FilterValue($item->getName(), $item->getName(), $filter->getName()));
        }

        return $customFilter;
    }

    private static function handleRangeSliderFilter($filter): RangeSliderFilter
    {
        $customFilter = new RangeSliderFilter($filter->getName(), $filter->getDisplayName());
        $unit = $filter->getUnit();
        $step = $filter->getStepSize();

        if ($unit !== null) {
            $customFilter->setUnit($unit);
        }

        if ($step !== null) {
            $customFilter->setStep($step);
        }

        if ($filter->getTotalRange()) {
            $customFilter->setTotalRange([
                self::FILTER_RANGE_MIN => $filter->getTotalRange()->getMin(),
                self::FILTER_RANGE_MAX => $filter->getTotalRange()->getMax(),
            ]);
        }

        if ($filter->getSelectedRange()) {
            $customFilter->setSelectedRange([
                self::FILTER_RANGE_MIN => $filter->getSelectedRange()->getMin(),
                self::FILTER_RANGE_MAX => $filter->getSelectedRange()->getMax(),
            ]);
        }

        foreach ($filter->getValues() as $item) {
            $customFilter->addValue(new FilterValue($item->getName(), $item->getName(), $filter->getName()));
        }

        if ($filter->getTotalRange()->getMin() && $filter->getTotalRange()->getMax()) {
            $customFilter->setMin($filter->getTotalRange()->getMin());
            $customFilter->setMax($filter->getTotalRange()->getMax());
        } else {
            $filterItems = array_values($filter->getValues());

            $firstFilterItem = current($filterItems);
            if ($firstFilterItem?->getMin()) {
                $customFilter->setMin($firstFilterItem->getMin());
            }

            $lastFilterItem = end($filterItems);
            if ($lastFilterItem?->getMax()) {
                $customFilter->setMax($lastFilterItem->getMax());
            }
        }

        return $customFilter;
    }
}
