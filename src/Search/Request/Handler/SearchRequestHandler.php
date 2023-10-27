<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler;

use Nosto\NostoIntegration\Search\Request\SearchRequest;
use Nosto\NostoIntegration\Search\Response\GraphQL\GraphQLResponseParser;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareEvent;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

class SearchRequestHandler extends SearchNavigationRequestHandler
{
    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function handleRequest(Request $request, Criteria $criteria): void
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

//        if ($responseParser->getLandingPageExtension()) {
//            $this->handleLandingPage($responseParser, $event);
//
//            return;
//        }

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

    //    protected function handleLandingPage(ResponseParser $responseParser, ShopwareEvent $event): void
    //    {
    //        $event->getContext()->addExtension(
    //            'flLandingPage',
    //            $responseParser->getLandingPageExtension()
    //        );
    //    }
}
