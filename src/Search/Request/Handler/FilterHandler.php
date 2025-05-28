<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler;

use JsonException;
use Nosto\NostoIntegration\Decorator\Storefront\Framework\Cookie\NostoCookieProvider;
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
     * @throws JsonException
     */
    public function handleFilters(
        Request $request,
        Criteria $criteria,
        SearchOperation $searchOperation,
        bool $newRequest = true,
    ): void {
        $selectedFilters = $request->query->all();

        if (empty($selectedFilters)) {
            return;
        }

        if ($newRequest) {
            $this->handleNewRequest($selectedFilters, $criteria, $searchOperation);
        } else {
            $this->handleExistingRequest($selectedFilters, $request, $searchOperation);
        }
    }

    private function handleNewRequest(
        array $selectedFilters,
        Criteria $criteria,
        SearchOperation $searchOperation,
    ): void {
        $availableFilterIds = $this->fetchAvailableFilterIds($criteria);
        /** @var IdToFieldMapping $filterMapping */
        $filterMapping = $criteria->getExtension('nostoFilterMapping');

        foreach ($selectedFilters as $filterId => $filterValues) {
            if (!is_string($filterValues)) {
                continue;
            }

            $this->processFilterValues($filterId, $filterValues, $searchOperation, $availableFilterIds, $filterMapping);
        }
    }

    private function handleExistingRequest(
        array $selectedFilters,
        Request $request,
        SearchOperation $searchOperation,
    ): void {
        $cookieValue = $request->cookies->get(NostoCookieProvider::NOSTO_FILTERS_KEY);
        if (is_null($cookieValue)) {
            return;
        }

        try {
            $nostoFilters = json_decode($cookieValue, true, 512, JSON_THROW_ON_ERROR);
            foreach ($selectedFilters as $filterId => $filterValues) {
                if (!is_string($filterValues)) {
                    continue;
                }

                $this->processFilterValues($filterId, $filterValues, $searchOperation, [], null, $nostoFilters);
            }
        } catch (JsonException) {
            // Invalid cookie value, ignore
            return;
        }
    }

    private function processFilterValues(
        string $filterId,
        string $filterValues,
        SearchOperation $searchOperation,
        array $availableFilterIds = [],
        ?IdToFieldMapping $filterMapping = null,
        ?array $nostoFilters = null,
    ): void {
        foreach ($this->getFilterValues($filterValues) as $filterValue) {
            if ($filterMapping !== null) {
                $this->handleFilterWithMapping(
                    $filterId,
                    $filterValue,
                    $searchOperation,
                    $availableFilterIds,
                    $filterMapping,
                );
            } else {
                $this->handleFilterWithNostoFilters(
                    $filterId,
                    $filterValue,
                    $searchOperation,
                    $nostoFilters,
                );
            }
        }
    }

    private function handleFilterWithMapping(
        string $filterId,
        string $filterValue,
        SearchOperation $searchOperation,
        array $availableFilterIds,
        IdToFieldMapping $filterMapping,
    ): void {
        if ($this->isRangeSliderFilter($filterId)) {
            $this->handleRangeSliderFilter($filterId, $filterValue, $searchOperation, $filterMapping);
            return;
        }

        $filterField = $filterMapping->getMapping($filterId);
        if (!$filterField) {
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

    private function handleFilterWithNostoFilters(
        string $filterId,
        string $filterValue,
        SearchOperation $searchOperation,
        ?array $nostoFilters,
    ): void {
        if ($nostoFilters === null) {
            return;
        }

        if ($this->isRangeSliderFilter($filterId)) {
            $this->handleRangeSliderFilter($filterId, $filterValue, $searchOperation, $nostoFilters);
            return;
        }

        if (!array_key_exists($filterId, $nostoFilters)) {
            return;
        }

        $filterField = $nostoFilters[$filterId];
        if ($this->isRatingFilter($filterField)) {
            $this->handleRatingFilter($filterField, $filterValue, $searchOperation);
            return;
        }

        if (in_array($filterId, $nostoFilters, true)) {
            $this->handlePropertyFilter($filterField, $filterValue, $searchOperation);
        }
    }

    private function handleRangeSliderFilter(
        string $filterId,
        mixed $filterValue,
        SearchOperation $searchOperation,
        IdToFieldMapping|array $filterSource,
    ): void {
        $isMin = mb_strpos($filterId, self::MIN_PREFIX) === 0;
        $baseFilterId = mb_substr(
            $filterId,
            mb_strlen($isMin ? self::MIN_PREFIX : self::MAX_PREFIX),
        );

        $filterField = $filterSource instanceof IdToFieldMapping
            ? $filterSource->getMapping($baseFilterId)
            : $filterSource[$baseFilterId];

        if ($isMin) {
            $searchOperation->addRangeFilter($filterField, $filterValue);
        } else {
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