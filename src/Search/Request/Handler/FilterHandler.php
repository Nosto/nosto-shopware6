<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler;

use Nosto\NostoIntegration\Search\Response\GraphQL\Filter\Filter;
use Nosto\NostoIntegration\Search\Response\GraphQL\Filter\RangeSliderFilter;
use Nosto\NostoIntegration\Search\Response\GraphQL\Filter\RatingFilter;
use Nosto\NostoIntegration\Struct\FiltersExtension;
use Nosto\NostoIntegration\Struct\IdToFieldMapping;
use Nosto\Operation\Search\SearchOperation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\HttpFoundation\Request;

class FilterHandler
{
    public const FILTER_DELIMITER = '|';

    protected const MIN_PREFIX = 'min-';

    protected const MAX_PREFIX = 'max-';

    /**
     * Sets all requested filters to the Nosto API request.
     */
    public function handleFilters(
        Request $request,
        Criteria $criteria,
        SearchOperation $searchOperation,
    ): void {
        $selectedFilters = $request->query->all();
        $availableFilterIds = $this->fetchAvailableFilterIds($criteria);
        /** @var IdToFieldMapping $filterMapping */
        $filterMapping = $criteria->getExtension('nostoFilterMapping');

        if ($selectedFilters) {
            foreach ($selectedFilters as $filterId => $filterValues) {
                if (!is_string($filterValues)) {
                    continue;
                }

                foreach ($this->getFilterValues($filterValues) as $filterValue) {
                    $this->handleFilter(
                        $filterId,
                        $filterValue,
                        $searchOperation,
                        $availableFilterIds,
                        $filterMapping,
                    );
                }
            }
        }
    }

    protected function handleFilter(
        string $filterId,
        string $filterValue,
        SearchOperation $searchOperation,
        array $availableFilterIds,
        IdToFieldMapping $filterMapping,
    ): void {
        // Range Slider filters in Shopware are prefixed with min-/max-. We manually need to remove this and send
        // the appropriate parameters to our API.
        if ($this->isRangeSliderFilter($filterId)) {
            $this->handleRangeSliderFilter($filterId, $filterValue, $searchOperation, $filterMapping);

            return;
        }

        if (!$filterField = $filterMapping->getMapping($filterId)) {
            return;
        }

        if ($this->isRatingFilter($filterField)) {
            $this->handleRatingFilter($filterField, $filterValue, $searchOperation);

            return;
        }

        if (in_array($filterId, $availableFilterIds, true)) {
            $this->handlePropertyFilter($filterField, $filterValue, $searchOperation);
        }
    }

    protected function handleRangeSliderFilter(
        string $filterId,
        mixed $filterValue,
        SearchOperation $searchOperation,
        IdToFieldMapping $fieldMapping,
    ): void {
        if (mb_strpos($filterId, self::MIN_PREFIX) === 0) {
            $filterId = mb_substr($filterId, mb_strlen(self::MIN_PREFIX));
            $filterField = $fieldMapping->getMapping($filterId);
            $searchOperation->addRangeFilter($filterField, $filterValue);
        } else {
            $filterId = mb_substr($filterId, mb_strlen(self::MAX_PREFIX));
            $filterField = $fieldMapping->getMapping($filterId);
            $searchOperation->addRangeFilter($filterField, null, $filterValue);
        }
    }

    protected function handleRatingFilter(
        string $filterField,
        mixed $filterValue,
        SearchOperation $searchOperation,
    ): void {
        $searchOperation->addRangeFilter($filterField, $filterValue);
    }

    protected function isRangeSliderFilter(string $id): bool
    {
        return $this->isMinRangeSlider($id) || $this->isMaxRangeSlider($id);
    }

    protected function isRatingFilter(string $field): bool
    {
        return $field === Filter::RATING_FILTER_FIELD;
    }

    /**
     * Fetches all available filter names. This is needed to distinguish between standard Shopware query parameters
     * like "q", "sort", etc. and real filters.

     * @return string[]
     */
    protected function fetchAvailableFilterIds(Criteria $criteria): array
    {
        $availableFilters = [];
        /** @var FiltersExtension $filtersExtension */
        $filtersExtension = $criteria->getExtension('nostoFilters');

        $filters = $filtersExtension->getFilters();
        foreach ($filters as $filter) {
            $availableFilters[] = $filter->getId();
        }

        return $availableFilters;
    }

    /**
     * Submitting multiple filter values for the same filter e.g. size=20 and size=21, will not set
     * the same query parameter twice. Instead they have the same key and their values are
     * imploded via a special character (|). The query parameter looks like ?size=20|21.
     * This method simply explodes the given string into filter values.
     *
     * @return string[]
     */
    protected function getFilterValues(string $filterValues): array
    {
        return explode(self::FILTER_DELIMITER, $filterValues);
    }

    private function isMinRangeSlider(string $id): bool
    {
        return mb_strpos($id, self::MIN_PREFIX) === 0;
    }

    private function isMaxRangeSlider(string $id): bool
    {
        return mb_strpos($id, self::MAX_PREFIX) === 0;
    }

    private function handlePropertyFilter(
        string $filterField,
        string $filterValue,
        SearchOperation $searchOperation,
    ): void {
        $searchOperation->addValueFilter($filterField, $filterValue);
    }

    /**
     * @return array<string, mixed>
     */
    public function handleAvailableFilters(Criteria $criteria): array
    {
        /** @var FiltersExtension $availableFilters */
        $availableFilters = $criteria->getExtension('nostoAvailableFilters');
        /** @var FiltersExtension $allFilters */
        $allFilters = $criteria->getExtension('nostoFilters');

        return $this->parseNostoFiltersForShopware($availableFilters, $allFilters);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseNostoFiltersForShopware(
        FiltersExtension $availableFilters,
        FiltersExtension $allFilters,
    ): array {
        $result = [];

        foreach ($allFilters->getFilters() as $filterWithAllValues) {
            $filterName = $filterWithAllValues->getId();
            if (!$filter = $availableFilters->getFilter($filterName)) {
                $result[$filterName]['entities'] = [];
                continue;
            }

            $values = $filter->getValues();

            if ($filter instanceof RatingFilter) {
                $result[$filterName]['max'] = $filter->getMaxPoints();
            } else {
                $filterValues = [];

                if ($filter instanceof RangeSliderFilter) {
                    $filterValues[] = [
                        'min' => $filter->getMin(),
                        'max' => $filter->getMax(),
                    ];
                } else {
                    foreach ($values as $value) {
                        $filterValues[] = [
                            'id' => $value->getTranslated()->getName(),
                            'translated' => [
                                'name' => $value->getTranslated()->getName(),
                            ],
                        ];
                    }
                }

                $entityValues = [
                    'translated' => [
                        'name' => $filter->getName(),
                    ],
                    'options' => $filterValues,
                ];

                $result[$filterName]['entities'][] = $entityValues;
            }
        }

        $actualResult['properties']['entities'] = $result;

        return array_merge($actualResult, $result);
    }
}
