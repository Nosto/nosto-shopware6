<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Api;

use Composer\InstalledVersions;
use Monolog\Logger;
use Nosto\NostoIntegration\Decorator\Storefront\Framework\Cookie\NostoCookieProvider;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Search\Request\Handler\AbstractRequestHandler;
use Nosto\NostoIntegration\Search\Request\Handler\NavigationRequestHandler;
use Nosto\NostoIntegration\Search\Request\Handler\SearchRequestHandler;
use Nosto\NostoIntegration\Search\Request\Handler\SortingHandlerService;
use Nosto\NostoIntegration\Service\FilterPayloadService;
use Nosto\NostoIntegration\Struct\NostoService;
use Nosto\NostoIntegration\Struct\Pagination;
use Nosto\NostoIntegration\Utils\SearchHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

class SearchService
{
    private const FILTER_REQUEST_LIMIT = 0;

    /**
     * Query parameters used by Shopware listings that are never Nosto filter ids.
     */
    private const NON_FILTER_PARAMS = [
        'search',
        'p',
        'order',
        'limit',
        'mode',
        'navigationId',
        'no-aggregations',
        'only-aggregations',
        'reduce-aggregations',
    ];

    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly PaginationService $paginationService,
        private readonly SortingHandlerService $sortingHandlerService,
        private readonly Logger $logger,
        private readonly EntityRepository $categoryRepository,
        private readonly FilterPayloadService $filterPayloadService,
        private readonly SystemConfigService $systemConfigService,
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
            $handler->fetchResults($request, $criteria, $context);
        }
    }

    protected function hasFilterParams(Request $request): bool
    {
        foreach (array_keys($request->query->all()) as $param) {
            if (!in_array((string) $param, self::NON_FILTER_PARAMS, true)) {
                return true;
            }
        }

        return false;
    }

    protected function allowRequest(Request $request, SalesChannelContext $context): bool
    {
        return SearchHelper::shouldHandleRequest(
            $context,
            $this->configProvider,
            SearchHelper::isNavigationPage($request),
            $request,
        );
    }

    protected function handleRequest(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context,
        AbstractRequestHandler $requestHandler,
    ): void {
        if ($criteria->getLimit() === null) {
            $criteria->setLimit($this->resolveLimit($request, $context));
        }
        $criteria->setOffset($this->paginationService->getRequestOffset($request, $criteria->getLimit()));

        // Retrieve the plugin version dynamically from InstalledVersions
        $pluginVersion = InstalledVersions::getPrettyVersion('nosto/nosto-integration') ?? 'unknown';
        // Set the header with plugin name and version for all search related requests
        $request->headers->set(
            'X-Nosto-Integration',
            sprintf('Shopware 6 Plugin %s', $pluginVersion),
        );

        $fetchedFilters = false;
        $filterCookie = $this->filterPayloadService->resolveCookiePayload(
            $request,
            NostoCookieProvider::NOSTO_FILTERS_KEY,
        );
        // The separate facets-only request is only needed when filters are already selected
        // and the full filter list cannot be restored from the cookie. Without selected
        // filters, the results response contains the complete filter list anyway.
        if (empty($filterCookie) && $this->hasFilterParams($request)) {
            $fetchedFilters = true;
            $this->fetchFilters($request, $criteria, $context, $requestHandler);
        }

        $requestHandler->fetchResults($request, $criteria, $context, $fetchedFilters);
    }

    protected function fetchFilters(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context,
        AbstractRequestHandler $requestHandler,
    ): void {
        try {
            $response = $requestHandler->sendRequest($request, $criteria, $context, self::FILTER_REQUEST_LIMIT);
            $filters = $requestHandler->parseFiltersFromResponse($response);
            $filterMapping = $requestHandler->parseFilterMappingFromResponse($response);

            $criteria->addExtension('nostoFilters', $filters);
            $criteria->addExtension('nostoFilterMapping', $filterMapping);

            $request->attributes->set(
                'setNostoCookie',
                json_encode($filterMapping->getMap(), JSON_THROW_ON_ERROR),
            );
        } catch (Throwable $e) {
            /** @var NostoService $nostoService */
            $nostoService = $context->getContext()->getExtension('nostoService');
            $nostoService->disable();

            $this->logger->error(
                sprintf('Error while fetching all filters: %s', $e->getMessage()),
            );
        }
    }

    protected function buildSearchRequestHandler(): SearchRequestHandler
    {
        return new SearchRequestHandler(
            $this->configProvider,
            $this->sortingHandlerService,
            $this->logger,
            $this->filterPayloadService,
        );
    }

    protected function buildNavigationRequestHandler(): NavigationRequestHandler
    {
        return new NavigationRequestHandler(
            $this->configProvider,
            $this->sortingHandlerService,
            $this->logger,
            $this->filterPayloadService,
            $this->categoryRepository,
        );
    }

    private function resolveLimit(Request $request, SalesChannelContext $context): int
    {
        $limit = $request->query->getInt('limit');

        if ($request->isMethod(Request::METHOD_POST)) {
            $limit = $request->request->getInt('limit', $limit);
        }

        if ($limit > 0) {
            return $limit;
        }

        $limit = $this->systemConfigService->getInt(
            'core.listing.productsPerPage',
            $context->getSalesChannelId(),
        );

        return $limit <= 0 ? Pagination::DEFAULT_LIMIT : $limit;
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
