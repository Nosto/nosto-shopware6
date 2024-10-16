<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Decorator\Core\Content\Product\SalesChannel\Search;

use Exception;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Traits\SearchResultHelper;
use Nosto\NostoIntegration\Utils\SearchHelper;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\CompositeListingProcessor;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\Search\ResolvedCriteriaProductSearchRoute;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @see \Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRoute
 */
class ProductSearchRoute extends AbstractProductSearchRoute
{
    use SearchResultHelper;

    public function __construct(
        private readonly AbstractProductSearchRoute $decorated,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ProductSearchBuilderInterface $searchBuilder,
        private readonly SalesChannelRepository $salesChannelProductRepository,
        private readonly CompositeListingProcessor $listingProcessor,
        private readonly ConfigProvider $configProvider,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): AbstractProductSearchRoute
    {
        return $this->decorated;
    }

    public function load(
        Request $request,
        SalesChannelContext $context,
        Criteria $criteria,
    ): ProductSearchRouteResponse {
        try {
            if (!SearchHelper::shouldHandleRequest($context, $this->configProvider)) {
                return $this->decorated->load($request, $context, $criteria);
            }

            if (!$request->get('search')) {
                throw RoutingException::missingRequestParameter('search');
            }

            if (!$request->get('order')) {
                $request->request->set('order', ResolvedCriteriaProductSearchRoute::DEFAULT_SEARCH_SORT);
            }

            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

            $criteria->addFilter(
                new ProductAvailableFilter(
                    $context->getSalesChannel()->getId(),
                    ProductVisibilityDefinition::VISIBILITY_SEARCH,
                ),
            );

            $this->searchBuilder->build($request, $criteria, $context);

            $this->listingProcessor->prepare($request, $criteria, $context);

            $query = $request->query->get('search');
            $result = $this->fetchProductsById($criteria, $context, $query);
            $productListing = ProductListingResult::createFrom($result);
            $productListing->addCurrentFilter('search', $query);

            $this->listingProcessor->process($request, $productListing, $context);

            $this->eventDispatcher->dispatch(
                new ProductSearchResultEvent($request, $productListing, $context),
                ProductEvents::PRODUCT_SEARCH_RESULT,
            );

            return new ProductSearchRouteResponse($productListing);
        } catch (RoutingException $e) {
            $this->logger->error('Routing exception occurred: ' . $e->getMessage());
            return $this->decorated->load($request, $context, $criteria);
        } catch (Exception $e) {
            $this->logger->error('An unexpected error occurred: ' . $e->getMessage());
            return $this->decorated->load($request, $context, $criteria);
        }
    }

    private function fetchProductsById(
        Criteria $criteria,
        SalesChannelContext $salesChannelContext,
        ?string $query,
    ): EntitySearchResult {
        if (empty($criteria->getIds())) {
            return $this->createEmptySearchResult($criteria, $salesChannelContext->getContext());
        }

        return $this->fetchProducts($criteria, $salesChannelContext, $query);
    }
}
