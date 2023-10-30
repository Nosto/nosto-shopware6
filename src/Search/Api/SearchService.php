<?php

namespace Nosto\NostoIntegration\Search\Api;

use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Search\Request\Handler\AbstractRequestHandler;
use Nosto\NostoIntegration\Search\Request\Handler\SearchRequestHandler;
use Nosto\NostoIntegration\Search\Request\Handler\SortingHandlerService;
use Nosto\NostoIntegration\Search\Response\GraphQL\GraphQLResponseParser;
use Nosto\NostoIntegration\Struct\FiltersExtension;
use Nosto\NostoIntegration\Struct\IdToFieldMapping;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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

    public function doSearch(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        if ($this->allowRequest()) {
            $searchRequestHandler = $this->buildSearchRequestHandler();

            $this->handleRequest($request, $criteria, $context, $searchRequestHandler);
        }
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

        $this->fetchFilters($request, $criteria, $handler);
        $this->fetchSelectableFilters($request, $criteria, $handler);
    }

    protected function allowRequest(): bool
    {
        return $this->configProvider->isSearchEnabled();
    }

    protected function handleRequest(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context,
        AbstractRequestHandler $requestHandler,
    ): void {
        $criteria->setOffset($this->paginationService->getRequestOffset($request, $criteria->getLimit()));

        $this->fetchFilters($request, $criteria, $requestHandler);
        $requestHandler->fetchProducts($request, $criteria, $context);
    }

    protected function fetchFilters(
        Request $request,
        Criteria $criteria,
        AbstractRequestHandler $requestHandler
    ): void {
        try {
            $response = $requestHandler->sendRequest($request, $criteria, self::FILTER_REQUEST_LIMIT);
            $filters = $this->parseFiltersFromResponse($response);
            $filterMapping = $this->parseFilterMappingFromResponse($response);

            $criteria->addExtension('nostoFilters', $filters);
            $criteria->addExtension('nostoFilterMapping', $filterMapping);
        } catch (Throwable $e) {
        }
    }

    protected function fetchSelectableFilters(
        Request $request,
        Criteria $criteria,
        AbstractRequestHandler $requestHandler
    ): void {
        try {
            $response = $requestHandler->sendRequest($request, $criteria, self::FILTER_REQUEST_LIMIT);
            $response = $this->parseFiltersFromResponse($response);

            $criteria->addExtension('nostoAvailableFilters', $response);
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

    protected function parseFiltersFromResponse(stdClass $response): FiltersExtension
    {
        $responseParser = new GraphQLResponseParser($response);
        return $responseParser->getFiltersExtension();
    }

    protected function parseFilterMappingFromResponse(stdClass $response): IdToFieldMapping
    {
        $responseParser = new GraphQLResponseParser($response);
        return $responseParser->getFilterMapping();
    }
}
