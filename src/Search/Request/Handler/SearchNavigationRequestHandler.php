<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler;

use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Search\Request\SearchRequest;
use Nosto\NostoIntegration\Search\Response\GraphQL\GraphQLResponseParser;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareEvent;
use stdClass;

abstract class SearchNavigationRequestHandler
{
    /**
     * Contains criteria variable keys, which have been added in newer Shopware versions.
     * If they're not set (e.g. an older Shopware version), these values will be set to null by default.
     */
    private const NEW_CRITERIA_VARS = [
        'includes',
        'title',
    ];

    public function __construct(
        protected readonly ConfigProvider $configProvider,
        protected readonly SortingHandlerService $sortingHandlerService,
        protected ?FilterHandler $filterHandler = null
    ) {
        $this->filterHandler = $filterHandler ?? new FilterHandler();
    }

    abstract public function handleRequest(ShopwareEvent $event): void;

    /**
     * Sends a request to the FINDOLOGIC service based on the given event and the responsible request handler.
     *
     * @param int|null $limit limited amount of products
     */
    abstract public function doRequest(ShopwareEvent $event, ?int $limit = null): stdClass;

    public function sendRequest(SearchRequest $searchNavigationRequest): stdClass
    {
        return $searchNavigationRequest->execute();
    }

    protected function setPaginationParams(
        ShopwareEvent|ProductSearchCriteriaEvent $event,
        SearchRequest $request,
        ?int $limit,
    ): void {
        $request->setFrom($event->getCriteria()->getOffset());
        $request->setSize($limit ?? $event->getCriteria()->getLimit());
    }

    protected function assignCriteriaToEvent(ShopwareEvent|ProductListingCriteriaEvent $event, Criteria $criteria): void
    {
        $vars = $criteria->getVars();

        if (!empty($vars)) {
            $vars['limit'] = $event->getCriteria()->getLimit();

            // Set criteria default vars to allow compatibility with older Shopware versions.
            foreach (self::NEW_CRITERIA_VARS as $varName) {
                if (!array_key_exists($varName, $vars)) {
                    $vars[$varName] = null;
                }
            }
        }

        $event->getCriteria()->assign($vars);
    }

    protected function setPagination(
        Criteria $criteria,
        GraphQLResponseParser $responseParser,
        ?int $limit,
        ?int $offset
    ): void {
        $pagination = $responseParser->getPaginationExtension($limit, $offset);
        $criteria->addExtension('flPagination', $pagination);
    }
}
