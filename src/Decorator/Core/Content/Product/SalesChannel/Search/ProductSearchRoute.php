<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Decorator\Core\Content\Product\SalesChannel\Search;

use Exception;
use Nosto\Model\Analytics\AnalyticsSearchMetadata;
use Nosto\NostoIntegration\Enums\ProductIdentifierOptions;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Traits\SearchResultHelper;
use Nosto\NostoIntegration\Utils\SearchHelper;
use Nosto\Operation\Search\AnalyticsSearchTracking;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\CompositeListingProcessor;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRouteResponse;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Uuid\Uuid;
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
            $originalRequest = clone $request;
            $originalContext = clone $context;
            $originalCriteria = clone $criteria;

            if (!SearchHelper::shouldHandleRequest($context, $this->configProvider)) {
                return $this->decorated->load($request, $context, $criteria);
            }

            if (!$request->get('search')) {
                throw RoutingException::missingRequestParameter('search');
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

            if (!$result->getElements()) {
                return $this->decorated->load($originalRequest, $originalContext, $originalCriteria);
            }

            $productListing = ProductListingResult::createFrom($result);
            $productListing->addCurrentFilter('search', $query);

            $this->listingProcessor->process($request, $productListing, $context);

            $this->eventDispatcher->dispatch(
                new ProductSearchResultEvent($request, $productListing, $context),
                ProductEvents::PRODUCT_SEARCH_RESULT,
            );
            $this->sendImpressionAnalytics($context, $productListing, $request);
            return new ProductSearchRouteResponse($productListing);
        } catch (RoutingException $e) {
            $this->logger->error('Routing exception occurred: ' . $e->getMessage());
            return $this->decorated->load($request, $context, $criteria);
        } catch (Exception $e) {
            $this->logger->error('An unexpected error occurred: ' . $e->getMessage());
            return $this->decorated->load($request, $context, $criteria);
        }
    }

    private function sendImpressionAnalytics(
        SalesChannelContext $context,
        ProductListingResult $productListing,
        Request $request,
    ): void {
        try {
            $shouldSendImpression = $this->configProvider->isEnabledSearchImpressions(
                $context->getSalesChannelId(),
                $context->getLanguageId(),
            );
            if (!$shouldSendImpression) {
                return;
            }
            $merchantId = $this->configProvider->getAccountId($context->getSalesChannelId(), $context->getLanguageId());
            $productIdentifier = $this->configProvider->getProductIdentifier(
                $context->getSalesChannelId(),
                $context->getLanguageId(),
            );
            $productIds = array_map(
                fn (ProductEntity $product) => $productIdentifier === ProductIdentifierOptions::PRODUCT_NUMBER ? $product->getProductNumber() : $product->getId(),
                array_values($productListing->getElements()),
            );
            //php changes the . to _ in the cookie
            $sessionId = $request->cookies->get("2c_cId");
            if (!$sessionId) {
                return;
            }
            $userAgent = $request->headers->get('User-Agent');
            $tracker = new AnalyticsSearchTracking($merchantId, $sessionId, $userAgent);
            $page = $productListing->getPage();
            $resultId = vsprintf('%s%s%s%s-%s%s-%s%s-%s%s-%s%s%s%s%s%s', str_split(Uuid::randomHex(), 2));
            $metadata = new AnalyticsSearchMetadata(
                $request->get('search') ?? null,
                $resultId,
                //isOrganic
                true,
                //isAutoCorrect
                true,
                //isAutoComplete
                false,
                //isKeyword
                false,
                //isSorted
                $request->get('order') != null,
                //hasResults
                true,
                //isRefined
                false,
            );

            $tracker->impression($metadata, $productIds, $page);
            //we need to know about the resultId that was used in the impression for the click analytic
            $productListing->setExtensions([
                "nosto_result_id" => $resultId,
            ]);
        } catch (\Exception $e) {
            //@ToDo maybe send the the error to the nosto
            //Just log the error and proceed
            $this->logger->error('Unable to send search impression analytics due to:' . $e->getMessage());
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
