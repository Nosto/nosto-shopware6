<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler;

use Monolog\Logger;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Search\Request\SearchRequest;
use Nosto\NostoIntegration\Search\Response\GraphQL\GraphQLResponseParser;
use Nosto\Result\Graphql\Search\SearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

abstract class AbstractRequestHandler
{
    public function __construct(
        protected readonly ConfigProvider $configProvider,
        protected readonly SortingHandlerService $sortingHandlerService,
        protected readonly Logger $logger,
        protected ?FilterHandler $filterHandler = null
    ) {
        $this->filterHandler = $filterHandler ?? new FilterHandler();
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
        ?int $limit = null
    ): SearchResult;

    public function fetchProducts(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        $originalCriteria = clone $criteria;

        try {
            $response = $this->sendRequest($request, $criteria, $context);
            $responseParser = new GraphQLResponseParser($response);
        } catch (Throwable $e) {
            $this->logger->error(
                sprintf('Error while fetching the products: %s', $e->getMessage())
            );
            return;
        }

        if ($redirect = $responseParser->getRedirectExtension()) {
            $this->handleRedirect($context, $redirect);

            return;
        }

        $criteria->setIds($responseParser->getProductIds());

        $this->setPagination(
            $criteria,
            $responseParser,
            $originalCriteria->getLimit(),
            $originalCriteria->getOffset()
        );
    }

    protected function setDefaultParams(Request $request, Criteria $criteria, SearchRequest $searchRequest, ?int $limit = null): void
    {
        $this->setPaginationParams($criteria, $searchRequest, $limit);
        $this->setSessionParamsFromCookies($request, $searchRequest);
        $this->sortingHandlerService->handle($searchRequest, $criteria);
        if ($criteria->hasExtension('nostoFilters')) {
            $this->filterHandler->handleFilters($request, $criteria, $searchRequest);
        }
    }

    protected function setPaginationParams(
        Criteria $criteria,
        SearchRequest $request,
        ?int $limit,
    ): void {
        $request->setFrom($criteria->getOffset() ?? 0);
        $request->setSize($limit ?? $criteria->getLimit());
    }

    protected function setPagination(
        Criteria $criteria,
        GraphQLResponseParser $responseParser,
        ?int $limit,
        ?int $offset
    ): void {
        $pagination = $responseParser->getPaginationExtension($limit, $offset);
        $criteria->addExtension('nostoPagination', $pagination);
    }

    protected function setSessionParamsFromCookies(Request $request, SearchRequest $searchRequest): void
    {
        if ($sessionParamsString = $request->cookies->get('nosto-search-session-params')) {
            $sessionParams = json_decode($sessionParamsString, true);
            $searchRequest->setSessionParams($sessionParams);
        }
    }
}
