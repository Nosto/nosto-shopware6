<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler;

use Nosto\NostoIntegration\Model\Nosto\Entity\Product\Builder;
use Nosto\Result\Graphql\Search\SearchResult;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
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

        $searchOperation->setQuery((string) $request->query->get('search'));
        $searchOperation->addValueFilter(
            'customFields.' . Builder::SHOW_SEARCH,
            'true',
        );
        $searchOperation->setResponseTimeout(3);
        $searchOperation->setConnectTimeout(3);
        return $searchOperation->execute();
    }
}
