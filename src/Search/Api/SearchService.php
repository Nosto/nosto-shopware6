<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Api;

use Monolog\Logger;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Search\Request\Handler\AbstractRequestHandler;
use Nosto\NostoIntegration\Search\Request\Handler\NavigationRequestHandler;
use Nosto\NostoIntegration\Search\Request\Handler\SearchRequestHandler;
use Nosto\NostoIntegration\Search\Request\Handler\SortingHandlerService;
use Nosto\NostoIntegration\Search\Response\GraphQL\GraphQLResponseParser;
use Nosto\NostoIntegration\Struct\FiltersExtension;
use Nosto\NostoIntegration\Struct\IdToFieldMapping;
use Nosto\NostoIntegration\Struct\NostoService;
use Nosto\NostoIntegration\Utils\SearchHelper;
use Nosto\Result\Graphql\Search\SearchResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

class SearchService
{
    private const FILTER_REQUEST_LIMIT = 0;

    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly PaginationService $paginationService,
        private readonly SortingHandlerService $sortingHandlerService,
        private readonly Logger $logger,
    ) {
    }

    public function doSearch(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        if ($this->allowRequest($request, $context)) {
            $searchRequestHandler = $this->buildSearchRequestHandler();

            $this->handleRequest($request, $criteria, $context, $searchRequestHandler);
        }
    }

    public function doNavigation(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        if ($this->allowRequest($request, $context)) {
            $navigationRequestHandler = $this->buildNavigationRequestHandler();

            $this->handleRequest($request, $criteria, $context, $navigationRequestHandler);
        }
    }

    public function doFilter(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        if ($this->allowRequest($request, $context)) {
            if (SearchHelper::isSearchPage($request)) {
                $handler = $this->buildSearchRequestHandler();
            } elseif (SearchHelper::isNavigationPage($request)) {
                $handler = $this->buildNavigationRequestHandler();
            } else {
                $this->disableNostoService($context->getContext());
                return;
            }

            $this->fetchFilters($request, $criteria, $context, $handler);
            $this->fetchSelectableFilters($request, $criteria, $context, $handler);
        }
    }

    protected function allowRequest(Request $request, SalesChannelContext $context): bool
    {
        return SearchHelper::shouldHandleRequest(
            $context,
            $this->configProvider,
            SearchHelper::isNavigationPage($request),
        );
    }

    protected function handleRequest(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context,
        AbstractRequestHandler $requestHandler,
    ): void {
        $criteria->setOffset($this->paginationService->getRequestOffset($request, $criteria->getLimit()));

        $this->fetchFilters($request, $criteria, $context, $requestHandler);
        $requestHandler->fetchProducts($request, $criteria, $context);
    }

    protected function fetchFilters(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context,
        AbstractRequestHandler $requestHandler,
    ): void {
        try {
            $response = $requestHandler->sendRequest($request, $criteria, $context, self::FILTER_REQUEST_LIMIT);
            $filters = $this->parseFiltersFromResponse($response);
            $filterMapping = $this->parseFilterMappingFromResponse($response);

            $criteria->addExtension('nostoFilters', $filters);
            $criteria->addExtension('nostoFilterMapping', $filterMapping);
        } catch (Throwable $e) {
            /** @var NostoService $nostoService */
            $nostoService = $context->getContext()->getExtension('nostoService');
            $nostoService->disable();

            $this->logger->error(
                sprintf('Error while fetching all filters: %s', $e->getMessage()),
            );
        }
    }

    protected function fetchSelectableFilters(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context,
        AbstractRequestHandler $requestHandler,
    ): void {
        try {
            $response = $requestHandler->sendRequest($request, $criteria, $context, self::FILTER_REQUEST_LIMIT);
            $response = $this->parseFiltersFromResponse($response);

            $criteria->addExtension('nostoAvailableFilters', $response);
        } catch (Throwable $e) {
            /** @var NostoService $nostoService */
            $nostoService = $context->getContext()->getExtension('nostoService');
            $nostoService->disable();

            $this->logger->error(
                sprintf('Error while fetching the available filters: %s', $e->getMessage()),
            );
        }
    }

    protected function buildSearchRequestHandler(): SearchRequestHandler
    {
        return new SearchRequestHandler(
            $this->configProvider,
            $this->sortingHandlerService,
            $this->logger,
        );
    }

    protected function buildNavigationRequestHandler(): NavigationRequestHandler
    {
        return new NavigationRequestHandler(
            $this->configProvider,
            $this->sortingHandlerService,
            $this->logger,
        );
    }

    protected function parseFiltersFromResponse(SearchResult $response): FiltersExtension
    {
        $responseParser = new GraphQLResponseParser($response);
        return $responseParser->getFiltersExtension();
    }

    protected function parseFilterMappingFromResponse(SearchResult $response): IdToFieldMapping
    {
        $responseParser = new GraphQLResponseParser($response);
        return $responseParser->getFilterMapping();
    }

    protected function disableNostoService(Context $context): void
    {
        /** @var ?NostoService $nostoService */
        $nostoService = $context->getExtension('nostoService');
        if (!$nostoService) {
            $nostoService = new NostoService();
            $context->addExtension('nostoService', $nostoService);
        }

        $nostoService->disable();
    }
}
