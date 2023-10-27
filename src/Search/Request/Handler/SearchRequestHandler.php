<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler;

use Nosto\NostoIntegration\Search\Request\SearchRequest;
use Nosto\NostoIntegration\Search\Response\GraphQL\GraphQLResponseParser;
use Nosto\NostoIntegration\Struct\Redirect;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

class SearchRequestHandler extends SearchNavigationRequestHandler
{
    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function handleRequest(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        $searchRequest = new SearchRequest($this->configProvider);
        $searchRequest->setQuery((string) $request->query->get('search'));
        $originalCriteria = clone $criteria;
        $this->sortingHandlerService->handle($searchRequest, $criteria);

        try {
            $response = $this->doRequest($request, $criteria);
            $responseParser = new GraphQLResponseParser($response);
        } catch (Throwable $e) {
            dd($e);
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

    public function doRequest(Request $request, Criteria $criteria, ?int $limit = null): stdClass
    {
        $searchRequest = new SearchRequest($this->configProvider);
        $searchRequest->setQuery((string) $request->query->get('search'));
        $this->setPaginationParams($criteria, $searchRequest, $limit);
        $this->sortingHandlerService->handle($searchRequest, $criteria);
        if ($criteria->hasExtension('nostoFilters')) {
            $this->filterHandler->handleFilters($request, $criteria, $searchRequest);
        }

        return $searchRequest->execute();
    }

    protected function handleRedirect(SalesChannelContext $context, Redirect $redirectExtension): void
    {
        $context->getContext()->addExtension(
            'nostoRedirect',
            $redirectExtension
        );
    }
}
