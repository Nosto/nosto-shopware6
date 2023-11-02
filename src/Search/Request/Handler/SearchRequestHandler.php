<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler;

use Nosto\NostoIntegration\Search\Request\SearchRequest;
use Nosto\NostoIntegration\Struct\Redirect;
use Nosto\Result\Graphql\Search\SearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class SearchRequestHandler extends AbstractRequestHandler
{
    public function sendRequest(Request $request, Criteria $criteria, ?int $limit = null): SearchResult
    {
        $searchRequest = new SearchRequest($this->configProvider);
        $this->setDefaultParams($request, $criteria, $searchRequest, $limit);

        $searchRequest->setQuery((string) $request->query->get('search'));

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
