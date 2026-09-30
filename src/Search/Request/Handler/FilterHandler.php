<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler;

use JsonException;
use Nosto\NostoIntegration\Decorator\Storefront\Framework\Cookie\NostoCookieProvider;
use Nosto\NostoIntegration\Search\Response\GraphQL\Filter\Filter;
use Nosto\NostoIntegration\Search\Response\GraphQL\Filter\RangeSliderFilter;
use Nosto\NostoIntegration\Search\Response\GraphQL\Filter\RatingFilter;
use Nosto\NostoIntegration\Service\FilterPayloadService;
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

    private readonly NostoFieldFilterParser $fieldFilterParser;

    public function __construct(
        private readonly FilterPayloadService $filterPayloadService,
    ) {
        $this->fieldFilterParser = new NostoFieldFilterParser();
    }

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
        // Keep $selectedFilters free of the PHP-mangled 'products_filter_*' keys, so a request
        // that only carries field-path filters takes the early return below instead of running
        // the id-keyed pass (which, on the cookie path, costs a resolveCookiePayload() lookup).
        $selectedFilters = $this->withoutFieldPathParameters($request->query->all());

        // Nosto's autocomplete and SERP links key filters by field path rather than facet id.
        // They must be read from the raw query string: PHP rewrites '.' to '_' in parameter
        // names, so they are not visible in $request->query at all. See ADS-5261.
        $this->applyFieldPathFilters($request, $searchOperation);

        if (empty($selectedFilters)) {
            return;
        }

        if ($newRequest) {
            $this->handleNewRequest($selectedFilters, $criteria, $searchOperation);
        } else {
            $this->handleExistingRequest($selectedFilters, $request, $searchOperation);
        }
    }

    // Selected-state rendering for these filters is deliberately not handled here; the panel
    // computes it client-side from window.location.search. See ADS-5261 follow-up 3.
    private function applyFieldPathFilters(Request $request, SearchOperation $searchOperation): void
    {
        $queryString = $request->server->get('QUERY_STRING');

        $filters = $this->fieldFilterParser->parseQueryString(
            is_string($queryString) ? $queryString : null,
        );

        // SearchOperation merges ranges per field, so a repeated parameter name would silently
        // overwrite the bounds of the earlier one. Keep the first range per field, which matches
        // how multiple ranges inside a single value are handled.
        $rangedFields = [];

        foreach ($filters as $filter) {
            $this->applyFieldFilter($filter, $searchOperation, $rangedFields);
        }
    }

    /**
     * Drops the PHP-mangled counterparts of the field-path parameters (dots rewritten to
     * underscores), so a field-path-only request leaves no id-keyed parameters behind and can
     * skip the id-based pass entirely.
     *
     * @param array<string, mixed> $selectedFilters
     * @return array<string, mixed>
     */
    private function withoutFieldPathParameters(array $selectedFilters): array
    {
        $remaining = [];

        foreach ($selectedFilters as $filterId => $filterValues) {
            if (is_string($filterId) && str_starts_with($filterId, NostoFieldFilterParser::MANGLED_FIELD_PREFIX)) {
                continue;
            }

            $remaining[$filterId] = $filterValues;
        }

        return $remaining;
    }

    /**
     * @param array<string, true> $rangedFields fields that already had a range applied in this
     *                                          request, by reference so later duplicates of the
     *                                          same parameter name can be skipped
     */
    private function applyFieldFilter(
        NostoFieldFilter $filter,
        SearchOperation $searchOperation,
        array &$rangedFields = [],
    ): void {
        // Rating is a stats facet in Nosto: the pre-existing facet-id route sends it through
        // addRangeFilter() as a lower bound, and a value filter would match nothing.
        if (!$filter->isRange() && $this->isRatingFilter($filter->field)) {
            foreach ($filter->values as $value) {
                $this->handleRatingFilter($filter->field, $value, $searchOperation);
            }

            return;
        }

        if ($filter->isRange()) {
            if (isset($rangedFields[$filter->field])) {
                return;
            }

            $rangedFields[$filter->field] = true;

            // addRangeFilter() merges into one range per field, so only the first range is
            // representable. Applying the rest would corrupt the bounds.
            $range = $filter->ranges[0];

            $searchOperation->addRangeFilter(
                $filter->field,
                $range['gte'] ?? $range['gt'] ?? null,
                $range['lte'] ?? $range['lt'] ?? null,
            );

            return;
        }

        foreach ($filter->values as $value) {
            $searchOperation->addValueFilter($filter->field, $value);
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
        $cookieValue = $this->filterPayloadService->resolveCookiePayload(
            $request,
            NostoCookieProvider::NOSTO_FILTERS_MAPPING_KEY,
        );
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

        if (array_key_exists($filterId, $nostoFilters)) {
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
        $filtersExtension = $criteria->getExtension('nostoFilters');
        if (!$filtersExtension instanceof FiltersExtension) {
            return $availableFilters;
        }

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
        $availableFilters = $criteria->getExtension('nostoAvailableFilters');
        if (!$availableFilters instanceof FiltersExtension) {
            return [];
        }

        $allFilters = $criteria->getExtension('nostoFilters');
        if (!$allFilters instanceof FiltersExtension) {
            $allFilters = $availableFilters;
        }

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
                            'count' => $value->getCount(),
                            'selected' => $value->getSelected(),
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
