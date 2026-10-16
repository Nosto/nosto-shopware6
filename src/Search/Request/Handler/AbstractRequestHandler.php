<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler;

use Monolog\Logger;
use Nosto\Model\Signup\Account;
use Nosto\NostoIntegration\Decorator\Storefront\Framework\Cookie\NostoCookieProvider;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Search\Request\SessionParamsProvider;
use Nosto\NostoIntegration\Search\Response\GraphQL\GraphQLResponseParser;
use Nosto\NostoIntegration\Service\FilterPayloadService;
use Nosto\NostoIntegration\Struct\FiltersExtension;
use Nosto\NostoIntegration\Struct\IdToFieldMapping;
use Nosto\NostoIntegration\Struct\Redirect;
use Nosto\Operation\Search\SearchOperation;
use Nosto\Request\Api\Token;
use Nosto\Result\Graphql\Search\SearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractRequestHandler
{
    protected readonly FilterHandler $filterHandler;

    protected readonly SessionParamsProvider $sessionParamsProvider;

    public function __construct(
        protected readonly ConfigProvider $configProvider,
        protected readonly SortingHandlerService $sortingHandlerService,
        protected readonly Logger $logger,
        protected readonly FilterPayloadService $filterPayloadService,
    ) {
        $this->filterHandler = new FilterHandler($this->filterPayloadService);
        $this->sessionParamsProvider = new SessionParamsProvider($this->logger);
    }

    /**
     * Sends a request to the Nosto service based on the given event and the responsible request handler.
     *
     * @param int|null $limit limited amount of products
     */
    abstract public function sendRequest(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context,
        ?int $limit = null,
    ): SearchResult;

    public function fetchResults(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context,
        $fetchedFilters = false,
    ): void {
        $originalCriteria = clone $criteria;

        try {
            $response = $this->sendRequest($request, $criteria, $context);
            $request->attributes->set('nostoSearchType', $response->getSearchType());
            $request->attributes->set('nostoSearchTypeReason', $response->getSearchTypeReason());
            $criteria->addExtension('nostoAvailableFilters', $this->parseFiltersFromResponse($response));
            $responseParser = $this->createResponseParser($response);

            if (!$fetchedFilters && $responseParser->getProductIds()) {
                $this->handleFiltersAndMapping($request, $criteria, $response, $responseParser);
            }

            if ($redirect = $responseParser->getRedirectExtension()) {
                $this->handleRedirect($context, $redirect);
                return;
            }

            // Runs after the merchant redirect rules, which keep priority.
            if ($canonical = $this->buildCanonicalFilterUrl($request, $criteria)) {
                $this->handleRedirect($context, new Redirect($canonical, false));
                return;
            }

            $this->updateCriteriaWithProductIds($criteria, $responseParser);
            if (!is_null($criteria->getLimit()) && !is_null($criteria->getOffset())) {
                $this->setPagination(
                    $criteria,
                    $responseParser,
                    $originalCriteria->getLimit(),
                    $originalCriteria->getOffset(),
                );
            }
        } catch (\Throwable $e) {
            $this->logger->error(
                'Error while fetching products: {message}',
                [
                    'message' => $e->getMessage(),
                    'exception' => $e,
                    'trace' => $e->getTraceAsString(),
                ],
            );
        }
    }

    private function handleFiltersAndMapping(
        Request $request,
        Criteria $criteria,
        SearchResult $response,
        GraphQLResponseParser $responseParser,
    ): void {
        $filterCookie = $this->filterPayloadService->resolveCookiePayload(
            $request,
            NostoCookieProvider::NOSTO_FILTERS_KEY,
        );
        $filterMappingCookie = $this->filterPayloadService->resolveCookiePayload(
            $request,
            NostoCookieProvider::NOSTO_FILTERS_MAPPING_KEY,
        );
        $isInitialSearch = $request->attributes->get('isInitialSearch');

        // USE NOSTO RESPONSE FILTERS
        if ($isInitialSearch || !$filterCookie || !$filterMappingCookie) {
            $filters = $this->parseFiltersFromResponse($response);
            $filterMapping = $this->parseFilterMappingFromResponse($response);
        } else {
            // USE COOKIE FILTERS
            $filters = ProductHelper::convertJsonToFilter($filterCookie);
            $filterMapping = ProductHelper::convertJsonToFilterMapping($filterMappingCookie);
        }

        $criteria->addExtension('nostoFilters', $filters);
        $criteria->addExtension('nostoFilterMapping', $filterMapping);

        $request->attributes->set(
            'setNostoCookie',
            json_encode($filterMapping->getMap(), JSON_THROW_ON_ERROR),
        );
    }

    /**
     * The mapping is only present once handleFiltersAndMapping() has run, which requires a
     * non-empty product result. A zero-result filtered search therefore does not canonicalise.
     */
    private function buildCanonicalFilterUrl(Request $request, Criteria $criteria): ?string
    {
        $mapping = $criteria->getExtension('nostoFilterMapping');
        $queryString = $request->server->get('QUERY_STRING');

        return (new CanonicalFilterUrlBuilder())->build(
            $request->getPathInfo(),
            is_string($queryString) ? $queryString : null,
            $mapping instanceof IdToFieldMapping ? $mapping : null,
        );
    }

    private function updateCriteriaWithProductIds(Criteria $criteria, GraphQLResponseParser $responseParser): void
    {
        if ($productIds = $responseParser->getProductIds()) {
            $criteria->setIds($productIds);
        }
    }

    protected function handleRedirect(SalesChannelContext $context, Redirect $redirectExtension): void
    {
        $context->getContext()->addExtension(
            'nostoRedirect',
            $redirectExtension,
        );
    }

    protected function getSearchOperation(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context,
        ?int $limit = null,
    ): SearchOperation {
        $channelId = $context->getSalesChannelId();
        $languageId = $context->getLanguageId();

        $account = $this->getAccount($channelId, $languageId);
        $searchOperation = $this->initializeSearchOperation($account, $channelId, $languageId);

        $this->configureSearchOperation(
            $searchOperation,
            $request,
            $criteria,
            $limit,
            $context,
        );

        return $searchOperation;
    }

    private function initializeSearchOperation(
        Account $account,
        string $channelId,
        string $languageId,
    ): SearchOperation {
        $searchOperation = new SearchOperation($account);
        $searchOperation->setAccountId($this->configProvider->getAccountId($channelId, $languageId));

        return $searchOperation;
    }

    private function configureSearchOperation(
        SearchOperation $searchOperation,
        Request $request,
        Criteria $criteria,
        ?int $limit,
        SalesChannelContext $context,
    ): void {
        $this->setPaginationParams($criteria, $searchOperation, $limit);
        $this->setSessionParams(
            $request,
            $searchOperation,
            $context->getSalesChannelId(),
            $context->getLanguageId(),
        );
        if ($this->configProvider->isEnabledMultiCurrency(
            $context->getSalesChannelId(),
            $context->getLanguageId(),
        )) {
            $this->setCurrency($context, $searchOperation);
        }
        $this->setAbTests($request, $searchOperation);
        $this->sortingHandlerService->handle($searchOperation, $criteria);
        $newReq = $this->shouldHandleAsNewRequest($request, $criteria);
        $this->filterHandler->handleFilters($request, $criteria, $searchOperation, $newReq);
    }

    private function shouldHandleAsNewRequest(Request $request, Criteria $criteria): bool
    {
        $filterCookie = $this->filterPayloadService->resolveCookiePayload(
            $request,
            NostoCookieProvider::NOSTO_FILTERS_KEY,
        );

        return empty($filterCookie) && $criteria->hasExtension('nostoFilters');
    }

    protected function getAccount(string $salesChannelId, string $languageId): Account
    {
        $account = new Account($this->configProvider->getAccountName($salesChannelId, $languageId));
        $account->addApiToken(
            new Token(Token::API_SEARCH, $this->configProvider->getSearchToken($salesChannelId, $languageId)),
        );

        return $account;
    }

    protected function setPaginationParams(
        Criteria $criteria,
        SearchOperation $searchOperation,
        ?int $limit,
    ): void {
        $searchOperation->setFrom($criteria->getOffset() ?? 0);
        $searchOperation->setSize($limit ?? $criteria->getLimit());
    }

    protected function setPagination(
        Criteria $criteria,
        GraphQLResponseParser $responseParser,
        ?int $limit,
        ?int $offset,
    ): void {
        $pagination = $responseParser->getPaginationExtension($limit, $offset);
        $criteria->addExtension('nostoPagination', $pagination);
    }

    protected function setCurrency(
        SalesChannelContext $context,
        SearchOperation $searchOperation,
    ): void {
        $currency = $context->getCurrency()?->getIsoCode();
        if ($currency) {
            $searchOperation->setCurrency($currency);
        }
    }

    protected function setAbTests(
        Request $request,
        SearchOperation $searchOperation,
    ): void {
        $cookieValue = $request->cookies->get("nosto_ab_tests");
        if ($cookieValue) {
            $abTests = json_decode($cookieValue, true);
            $searchOperation->setAbTests($abTests);
        }
    }

    protected function updateAbTestsCookie(
        Request $request,
        mixed $abTests,
    ): void {
        //sets attribute which is catched in NostoCookieSubscriber.php and cookie is created with the value of the attribute
        if ($abTests != null) {
            $request->attributes->set(
                'setNostoAbTestsCookie',
                json_encode($abTests),
            );
        }
    }

    protected function setSessionParams(
        Request $request,
        SearchOperation $searchOperation,
        $channelId,
        $languageId,
    ): void {
        // Same opt-out rule as the storefront script gets from FrontendSubscriber
        $doNotTrack = !$this->configProvider
            ->getCustomerDataMode($channelId, $languageId)
            ->shouldSendCustomerData((bool) $request->cookies->get(NostoCookieProvider::NOSTO_TRACK_COOKIE_KEY));

        $searchOperation->setSessionParams(
            $this->sessionParamsProvider->getSessionParams(
                $request,
                $this->configProvider->getAccountId($channelId, $languageId),
                $doNotTrack,
            ),
        );
    }

    public function parseFiltersFromResponse(SearchResult $response): FiltersExtension
    {
        return (new GraphQLResponseParser($response))->getFiltersExtension();
    }

    public function parseFilterMappingFromResponse(SearchResult $response): IdToFieldMapping
    {
        return (new GraphQLResponseParser($response))->getFilterMapping();
    }

    private function createResponseParser(SearchResult $response): GraphQLResponseParser
    {
        return new GraphQLResponseParser($response);
    }
}
