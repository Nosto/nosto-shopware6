<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler;

use Nosto\NostoIntegration\Search\Request\SearchRequest;
use Nosto\NostoIntegration\Search\Response\GraphQL\GraphQLResponseParser;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareEvent;
use stdClass;
use Throwable;

class SearchRequestHandler extends SearchNavigationRequestHandler
{
    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function handleRequest(ShopwareEvent|ProductSearchCriteriaEvent $event): void
    {
        $request = $event->getRequest();

        $searchRequest = new SearchRequest($this->configProvider);
        $searchRequest->setQuery((string) $request->query->get('search'));
        $originalCriteria = clone $event->getCriteria();
        $this->sortingHandlerService->handle($searchRequest, $event->getCriteria());

        try {
            $response = $this->doRequest($event);
            $responseParser = new GraphQLResponseParser($response, $this->configProvider);
        } catch (Throwable $e) {
            $this->assignCriteriaToEvent($event, $originalCriteria);

            return;
        }

//        if ($responseParser->getLandingPageExtension()) {
//            $this->handleLandingPage($responseParser, $event);
//
//            return;
//        }

//        $event->getContext()->addExtension(
//            'flSmartDidYouMean',
//            $responseParser->getSmartDidYouMeanExtension($event->getRequest())
//        );

        $criteria = new Criteria(
            $responseParser->getProductIds() === [] ? null : $responseParser->getProductIds()
        );
        $criteria->addExtensions($event->getCriteria()->getExtensions());

        //        $this->setPromotionExtension($event, $responseParser);

        $this->setPagination(
            $criteria,
            $responseParser,
            $originalCriteria->getLimit(),
            $originalCriteria->getOffset()
        );

        //        $this->setQueryInfoMessage($event, $responseParser->getQueryInfoMessage($event));
        $this->assignCriteriaToEvent($event, $criteria);
    }

    public function doRequest(ShopwareEvent|ProductSearchCriteriaEvent $event, ?int $limit = null): stdClass
    {
        $request = $event->getRequest();

        $searchRequest = new SearchRequest($this->configProvider);
        $searchRequest->setQuery((string) $request->query->get('search'));
        $this->setPaginationParams($event, $searchRequest, $limit);
        $this->sortingHandlerService->handle($searchRequest, $event->getCriteria());
        if ($event->getCriteria()->hasExtension('flFilters')) {
            $this->filterHandler->handleFilters($event, $searchRequest);
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
