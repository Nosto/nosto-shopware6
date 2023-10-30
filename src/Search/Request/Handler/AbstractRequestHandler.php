<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler;

use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Search\Request\SearchRequest;
use Nosto\NostoIntegration\Search\Response\GraphQL\GraphQLResponseParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use stdClass;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractRequestHandler
{
    public function __construct(
        protected readonly ConfigProvider $configProvider,
        protected readonly SortingHandlerService $sortingHandlerService,
        protected ?FilterHandler $filterHandler = null
    ) {
        $this->filterHandler = $filterHandler ?? new FilterHandler();
    }

    abstract public function fetchProducts(Request $request, Criteria $criteria, SalesChannelContext $context): void;

    /**
     * Sends a request to the Nosto service based on the given event and the responsible request handler.
     *
     * @param int|null $limit limited amount of products
     */
    abstract public function sendRequest(Request $request, Criteria $criteria, ?int $limit = null): stdClass;

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
