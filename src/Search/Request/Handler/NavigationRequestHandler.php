<?php

namespace Nosto\NostoIntegration\Search\Request\Handler;

use Nosto\NostoIntegration\Search\Request\SearchRequest;
use Nosto\Result\Graphql\Search\SearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\HttpFoundation\Request;

class NavigationRequestHandler extends AbstractRequestHandler
{
    public function sendRequest(Request $request, Criteria $criteria, ?int $limit = null): SearchResult
    {
        $searchRequest = new SearchRequest($this->configProvider);
        $this->setDefaultParams($request, $criteria, $searchRequest, $limit);

        $searchRequest->setCategoryId($request->get('navigationId'));

        return $searchRequest->execute();
    }
}
