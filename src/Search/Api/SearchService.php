<?php

namespace Nosto\NostoIntegration\Search\Api;

use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Search\Request\Handler\SearchNavigationRequestHandler;
use Nosto\NostoIntegration\Search\Request\Handler\SearchRequestHandler;
use Nosto\NostoIntegration\Search\Request\Handler\SortingHandlerService;
use Nosto\NostoIntegration\Search\Response\GraphQL\GraphQLResponseParser;
use Nosto\NostoIntegration\Struct\FiltersExtension;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

class SearchService
{
    private const FILTER_REQUEST_LIMIT = 0;

    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly PaginationService $paginationService,
        private readonly SortingHandlerService $sortingHandlerService,
        private readonly EntityRepository $categoryRepository
    ) {
    }

    public function doSearch(Request $request, Criteria $criteria): void
    {
        if ($this->allowRequest()) {
            $searchRequestHandler = $this->buildSearchRequestHandler();

            $this->handleRequest($request, $criteria, $searchRequestHandler);
        }
    }

    protected function handleRequest(
        Request $request,
        Criteria $criteria,
        SearchNavigationRequestHandler $requestHandler,
    ): void {
        $limit = $criteria->getLimit();
        $criteria->setLimit($limit);
        $criteria->setOffset($this->paginationService->getRequestOffset($request, $limit));

        $this->handleFilters($request, $criteria, $requestHandler);
        $requestHandler->handleRequest($request, $criteria);
    }

    protected function allowRequest(): bool
    {
        return $this->configProvider->isSearchEnabled();
    }

    protected function handleFilters(
        Request $request,
        Criteria $criteria,
        SearchNavigationRequestHandler $requestHandler
    ): void {
        try {
            $response = $requestHandler->doRequest($request, $criteria, self::FILTER_REQUEST_LIMIT);
            $filters = $this->parseFiltersFromResponse($response);

            $criteria->addExtension('nostoFilters', $filters);
        } catch (Throwable $e) {
        }
    }

    protected function buildSearchRequestHandler(): SearchRequestHandler
    {
        return new SearchRequestHandler(
            $this->configProvider,
            $this->sortingHandlerService
        );
    }

    public function doFilter(Request $request, Criteria $criteria): void
    {
        if (!$this->allowRequest()) {
            return;
        }

        $handler = $this->buildSearchRequestHandler();
        // TODO: Add correct check for search
        //        if (!$event instanceof ProductSearchCriteriaEvent) {
        //            return;
        //        }

        //        if (!$this->isCategoryPage($handler, $event)) {
        //            $handler = $this->buildSearchRequestHandler();
        //        }

        $this->handleFilters($request, $criteria, $handler);
        $this->handleSelectableFilters($request, $criteria, $handler, self::FILTER_REQUEST_LIMIT);
    }

    protected function handleSelectableFilters(
        Request $request,
        Criteria $criteria,
        SearchNavigationRequestHandler $requestHandler,
        ?int $limit
    ): void {
        $response = $requestHandler->doRequest($request, $criteria, $limit);
        $response = $this->parseFiltersFromResponse($response);

        $criteria->addExtension('nostoAvailableFilters', $response);
    }

    protected function parseFiltersFromResponse(stdClass $response): FiltersExtension
    {
        $responseParser = new GraphQLResponseParser($response, $this->configProvider);
        return $responseParser->getFiltersExtension();
    }
}
