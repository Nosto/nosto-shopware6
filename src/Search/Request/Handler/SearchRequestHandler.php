<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler;

use Nosto\Result\Graphql\Search\SearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class SearchRequestHandler extends AbstractRequestHandler
{
    public function sendRequest(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context,
        ?int $limit = null,
    ): SearchResult {
        $searchOperation = $this->getSearchOperation($request, $criteria, $context, $limit);
        // TODO: Adjust the code.
        $searchOperation->setQuery((string) $request->query->get('search'));
        $searchOperation->setResponseTimeout(3);
        $searchOperation->setConnectTimeout(3);
        $d =  $searchOperation->executeSw();

        if((empty($request->cookies->get('nostoCookieFilter')) && count($request->query->all()) == 1) || $limit === 0) {
            $request->attributes->set('setNostoCookieDemo', $d[0]);
        }

        return $d[1];
    }
}
