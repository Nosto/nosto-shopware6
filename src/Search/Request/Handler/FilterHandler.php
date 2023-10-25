<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler;

use FINDOLOGIC\FinSearch\Findologic\Response\Filter\BaseFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\CategoryFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\RangeSliderFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\RatingFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\Values\CategoryFilterValue;
use FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\Values\FilterValue;
use FINDOLOGIC\FinSearch\Struct\FiltersExtension;
use Nosto\NostoIntegration\Search\Request\SearchRequest;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

use Symfony\Component\HttpFoundation\Request;
use function array_merge;
use function end;
use function in_array;

class FilterHandler
{
    public const FILTER_DELIMITER = '|';

    protected const MIN_PREFIX = 'min-';

    protected const MAX_PREFIX = 'max-';

    /**
     * Sets all requested filters to the FINDOLOGIC API request.
     */
    public function handleFilters(
        Request $request,
        Criteria $criteria,
        SearchRequest $searchNavigationRequest
    ): void {
        $selectedFilters = $request->query->all();
        $availableFilterNames = $this->fetchAvailableFilterNames($criteria);

        if ($selectedFilters) {
            foreach ($selectedFilters as $filterName => $filterValues) {
                if (!is_string($filterValues)) {
                    continue;
                }
                foreach ($this->getFilterValues($filterValues) as $filterValue) {
                    $this->handleFilter(
                        $filterName,
                        $filterValue,
                        $searchNavigationRequest,
                        $availableFilterNames
                    );
                }
            }
        }
    }

    protected function handleFilter(
        string $filterName,
        string $filterValue,
        SearchRequest $searchNavigationRequest,
        array $availableFilterNames
    ): void {
        // Range Slider filters in Shopware are prefixed with min-/max-. We manually need to remove this and send
        // the appropriate parameters to our API.
        if ($this->isRangeSliderFilter($filterName)) {
            $this->handleRangeSliderFilter($filterName, $filterValue, $searchNavigationRequest);

            return;
        }

        if ($this->isRatingFilter($filterName)) {
            $searchNavigationRequest->addRangeFilter($filterName, $filterValue);

            return;
        }

        if (in_array($filterName, $availableFilterNames, true)) {
            // This resolves the SW-451 issue about filter value conflict in storefront
            if ($filterName !== BaseFilter::CAT_FILTER_NAME && $this->isPropertyFilter($filterName, $filterValue)) {
                $this->handlePropertyFilter($filterName, $filterValue, $searchNavigationRequest);
            } else {
                $searchNavigationRequest->addValueFilter($filterName, $filterValue);
            }
        }
    }

    protected function handleRangeSliderFilter(
        string $filterName,
        mixed $filterValue,
        SearchRequest $searchNavigationRequest
    ): void {
        if (mb_strpos($filterName, self::MIN_PREFIX) === 0) {
            $filterName = mb_substr($filterName, mb_strlen(self::MIN_PREFIX));
            $searchNavigationRequest->addRangeFilter($filterName, $filterValue, null);
        } else {
            $filterName = mb_substr($filterName, mb_strlen(self::MAX_PREFIX));
            $searchNavigationRequest->addRangeFilter($filterName, null, $filterValue);
        }
    }

    protected function isRangeSliderFilter(string $name): bool
    {
        return $this->isMinRangeSlider($name) || $this->isMaxRangeSlider($name);
    }

    /**
     * Fetches all available filter names. This is needed to distinguish between standard Shopware query parameters
     * like "q", "sort", etc. and real filters.

     * @return string[]
     */
    protected function fetchAvailableFilterNames(Criteria $criteria): array
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
     */
    protected function getFilterValues(string $filterValues): array
    {
        return explode(self::FILTER_DELIMITER, $filterValues);
    }

    private function isMinRangeSlider(string $name): bool
    {
        return mb_strpos($name, self::MIN_PREFIX) === 0;
    }

    private function isMaxRangeSlider(string $name): bool
    {
        return mb_strpos($name, self::MAX_PREFIX) === 0;
    }

    private function isRatingFilter(string $filterName): bool
    {
        return $filterName === BaseFilter::RATING_FILTER_NAME;
    }

    private function isPropertyFilter(string $filterName, string $filterValue): bool
    {
        return mb_strpos($filterValue, sprintf('%s%s', $filterName, FilterValue::DELIMITER)) === 0;
    }

    private function handlePropertyFilter(
        string $filterName,
        string $filterValue,
        SearchRequest $searchNavigationRequest
    ): void {
        $parsedFilterValue = explode(sprintf('%s%s', $filterName, FilterValue::DELIMITER), $filterValue);
        $filterValue = end($parsedFilterValue);
        $searchNavigationRequest->addValueFilter($filterName, $filterValue);
    }

    public function handleAvailableFilters(Criteria $criteria): array
    {
        /** @var FiltersExtension $availableFilters */
        $availableFilters = $criteria->getExtension('nostoAvailableFilters');
        /** @var FiltersExtension $allFilters */
        $allFilters = $criteria->getExtension('nostoFilters');

        return $this->parseFindologicFiltersForShopware($availableFilters, $allFilters);
    }

    private function parseFindologicFiltersForShopware(
        FiltersExtension $availableFilters,
        FiltersExtension $allFilters
    ): array {
        $result = [];
        $result[BaseFilter::RATING_FILTER_NAME]['max'] = 0;

        foreach ($allFilters->getFilters() as $filterWithAllValues) {
            $filterName = $filterWithAllValues->getId();
            if (!$filter = $availableFilters->getFilter($filterName)) {
                $result[$filterName]['entities'] = [];
                continue;
            }

            $values = $filter->getValues();

            if ($filter instanceof RatingFilter) {
                $result[RatingFilter::RATING_FILTER_NAME]['max'] = $filter->getMaxPoints();
            } else {
                $filterValues = [];

                if ($filter instanceof CategoryFilter) {
                    $this->handleCategoryFilters($values, $filterValues);
                } elseif ($filter instanceof RangeSliderFilter) {
                    $filterValues[] = [
                        'selectedRange' => $filter->getSelectedRange(),
                        'totalRange' => $filter->getTotalRange(),
                    ];
                } else {
                    foreach ($values as $value) {
                        $valueId = $value->getUuid() ?? $value->getId();
                        $filterValues[] = [
                            'id' => $valueId,
                            'translated' => [
                                'name' => $valueId,
                            ],
                        ];
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
                        'name' => $filter instanceof CategoryFilter ? $filter->getId() : $filter->getName(),
                    ],
                    'options' => $filterValues,
                ];

                $result[$filterName]['entities'][] = $entityValues;
            }
        }

        $actualResult['properties']['entities'] = $result;

        return array_merge($actualResult, $result);
    }

    /**
     * @param FilterValue[] $values
     * @param array<string,array> $filterValues
     */
    private function handleCategoryFilters(array $values, array &$filterValues): void
    {
        /** @var CategoryFilterValue $value */
        foreach ($values as $value) {
            $valueId = $value->getId();
            $filterValues[] = [
                'id' => $valueId,
                'translated' => [
                    'name' => $valueId,
                ],
            ];
            if ($value->getValues()) {
                $this->handleCategoryFilters($value->getValues(), $filterValues);
            }
        }
    }
}
