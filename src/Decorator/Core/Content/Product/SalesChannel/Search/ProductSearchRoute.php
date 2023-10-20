<?php

namespace Nosto\NostoIntegration\Decorator\Core\Content\Product\SalesChannel\Search;

use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Search\Api\SearchService;
use Nosto\NostoIntegration\Traits\SearchResultHelper;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\CompositeListingProcessor;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRouteResponse;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ProductSearchRoute extends AbstractProductSearchRoute
{
    use SearchResultHelper;

    public function __construct(
        private readonly AbstractProductSearchRoute $decorated,
        private readonly ProductSearchBuilderInterface $searchBuilder,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SalesChannelRepository $salesChannelProductRepository,
        private readonly ProductDefinition $definition,
        private readonly RequestCriteriaBuilder $criteriaBuilder,
        private readonly CompositeListingProcessor $listingProcessor,
        private readonly SearchService $searchService,
        private readonly ConfigProvider $configProvider
    ) {
    }

    public function getDecorated(): AbstractProductSearchRoute
    {
        return $this->decorated;
    }

    public function load(
        Request $request,
        SalesChannelContext $context,
        ?Criteria $criteria = null
    ): ProductSearchRouteResponse {
        $this->addElasticSearchContext($context);

        $criteria ??= $this->criteriaBuilder->handleRequest(
            $request,
            new Criteria(),
            $this->definition,
            $context->getContext()
        );

        $event = new ProductSearchCriteriaEvent($request, new Criteria(), $context);
        $shouldHandleRequest = $this->configProvider->isSearchEnabled();

        $criteria->addFilter(
            new ProductAvailableFilter(
                $context->getSalesChannel()->getId(),
                ProductVisibilityDefinition::VISIBILITY_SEARCH
            )
        );

        $this->searchBuilder->build($request, $criteria, $context);

        $this->listingProcessor->prepare($event->getRequest(), $event->getCriteria(), $event->getSalesChannelContext());

        if (!$shouldHandleRequest) {
            return $this->decorated->load($request, $context, $criteria);
        }

        $query = $event->getRequest()->query->get('search');
        $result = $this->doSearch($event->getCriteria(), $event->getSalesChannelContext(), $query);
        $result = ProductListingResult::createFrom($result);
        $result->addCurrentFilter('search', $query);

        $this->listingProcessor->process($event->getRequest(), $result, $event->getSalesChannelContext());

        return new ProductSearchRouteResponse($result);
    }

    protected function doSearch(
        Criteria $criteria,
        SalesChannelContext $salesChannelContext,
        ?string $query
    ): EntitySearchResult {
        $this->assignPaginationToCriteria($criteria);
        $this->addOptionsGroupAssociation($criteria);

        if (empty($criteria->getIds())) {
            return $this->createEmptySearchResult($criteria, $salesChannelContext->getContext());
        }

        return $this->fetchProducts($criteria, $salesChannelContext, $query);
    }

    private function addElasticSearchContext(SalesChannelContext $context): void
    {
        $context->getContext()->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
    }
}
