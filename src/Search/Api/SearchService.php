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
use stdClass;
use Throwable;

class SearchService
{
    private const FILTER_REQUEST_LIMIT = 0;

    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly SortingService $sortingService,
        private readonly PaginationService $paginationService,
        private readonly SortingHandlerService $sortingHandlerService,
        private readonly EntityRepository $categoryRepository
    ) {
    }

    public function doSearch(ProductSearchCriteriaEvent $event, ?int $limitOverride = null): void
    {
        $limit = $limitOverride ?? $event->getCriteria()->getLimit();

        if ($this->allowRequest($event)) {
            $searchRequestHandler = $this->buildSearchRequestHandler();

            $this->handleRequest($event, $searchRequestHandler, $limit);
        }
    }

    protected function handleRequest(
        ProductListingCriteriaEvent $event,
        SearchNavigationRequestHandler $requestHandler,
        ?int $limit
    ): void {
        $event->getCriteria()->setLimit($limit);
        $event->getCriteria()->setOffset($this->paginationService->getRequestOffset($event->getRequest(), $limit));

        $this->handleFilters($event, $requestHandler);
        $requestHandler->handleRequest($event);

        $this->sortingService->handleRequest($event, $requestHandler);
    }

    protected function allowRequest(ProductListingCriteriaEvent $event): bool
    {
        return $this->configProvider->isSearchEnabled();
    }

    protected function handleFilters(
        ProductListingCriteriaEvent $event,
        SearchNavigationRequestHandler $requestHandler
    ): void {
        try {
            $response = $requestHandler->doRequest($event, self::FILTER_REQUEST_LIMIT);
            $filters = $this->parseFiltersFromResponse($response, $event);

            $event->getCriteria()->addExtension('nostoFilters', $filters);
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

    public function doFilter(ProductListingCriteriaEvent $event): void
    {
        if (!$this->allowRequest($event)) {
            return;
        }

        $handler = $this->buildSearchRequestHandler();
        if (!$event instanceof ProductSearchCriteriaEvent) {
            return;
        }

        //        if (!$this->isCategoryPage($handler, $event)) {
        //            $handler = $this->buildSearchRequestHandler();
        //        }

        $this->handleFilters($event, $handler);
        $this->handleSelectableFilters($event, $handler, self::FILTER_REQUEST_LIMIT);
    }

    protected function handleSelectableFilters(
        ProductListingCriteriaEvent $event,
        SearchNavigationRequestHandler $requestHandler,
        ?int $limit
    ): void {
        $response = $requestHandler->doRequest($event, $limit);
        $response = $this->parseFiltersFromResponse($response, $event);

        $event->getCriteria()->addExtension('nostoAvailableFilters', $response);
    }

    protected function parseFiltersFromResponse(
        stdClass $response,
        ProductListingCriteriaEvent $event
    ): FiltersExtension {
        $responseParser = new GraphQLResponseParser($response, $this->configProvider);
        return $responseParser->getFiltersExtension();
    }
}
