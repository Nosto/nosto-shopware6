<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Decorator\Core\Content\Product\SalesChannel\Listing;

use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Traits\SearchResultHelper;
use Nosto\NostoIntegration\Utils\SearchHelper;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\CompositeListingProcessor;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\Search\ResolvedCriteriaProductSearchRoute;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class ProductListingRoute extends AbstractProductListingRoute
{
    use SearchResultHelper;

    public function __construct(
        private readonly AbstractProductListingRoute $decorated,
        private readonly SalesChannelRepository $salesChannelProductRepository,
        private readonly EntityRepository $categoryRepository,
        private readonly ProductStreamBuilderInterface $productStreamBuilder,
        private readonly CompositeListingProcessor $listingProcessor,
        private readonly ConfigProvider $configProvider,
    ) {
    }

    public function getDecorated(): AbstractProductListingRoute
    {
        return $this->decorated;
    }

    public function load(
        string $categoryId,
        Request $request,
        SalesChannelContext $context,
        Criteria $criteria = null,
    ): ProductListingRouteResponse {
        $shouldHandleRequest = SearchHelper::shouldHandleRequest($context, $this->configProvider, true);

        $isDefaultCategory = $categoryId === $context->getSalesChannel()->getNavigationCategoryId();
        if (!$shouldHandleRequest || $isDefaultCategory || !$this->isRouteSupported($request)) {
            SearchHelper::disableNostoWhenEnabled($context);

            return $this->decorated->load($categoryId, $request, $context, $criteria);
        }

        $criteria->addFilter(
            new ProductAvailableFilter(
                $context->getSalesChannel()->getId(),
                ProductVisibilityDefinition::VISIBILITY_ALL,
            ),
        );

        /** @var CategoryEntity $category */
        $category = $this->categoryRepository->search(
            new Criteria([$categoryId]),
            $context->getContext(),
        )->first();

        $streamId = $this->extendCriteria($context, $criteria, $category);

        $this->listingProcessor->prepare($request, $criteria, $context);

        $productListing = ProductListingResult::createFrom(
            $this->fetchProductsById($criteria, $context),
        );
        $productListing->addCurrentFilter('navigationId', $categoryId);
        $productListing->setStreamId($streamId);

        $this->listingProcessor->process($request, $productListing, $context);

        $productListing->getAvailableSortings()->removeByKey(
            ResolvedCriteriaProductSearchRoute::DEFAULT_SEARCH_SORT,
        );

        return new ProductListingRouteResponse($productListing);
    }

    private function isRouteSupported(Request $request): bool
    {
        // Nosto should never trigger on home page, even if there are categories that would allow it.
        if ($this->isHomePage($request)) {
            return false;
        }

        // In case request came from the home page, Nosto should not trigger on those.
        if ($this->isRequestFromHomePage($request)) {
            return false;
        }

        return true;
    }

    private function isHomePage(Request $request): bool
    {
        return $request->getPathInfo() === '/';
    }

    private function isRequestFromHomePage(Request $request): bool
    {
        if (!$request->isXmlHttpRequest()) {
            return false;
        }

        $referer = $request->headers->get('referer');
        if (!$referer || !is_string($referer)) {
            return false;
        }

        $refererPath = parse_url($request->headers->get('referer'), PHP_URL_PATH);
        $path = ltrim($refererPath, $request->getBasePath());

        return $path === '' || $path === '/';
    }

    private function extendCriteria(
        SalesChannelContext $salesChannelContext,
        Criteria $criteria,
        CategoryEntity $category,
    ): ?string {
        $supportsProductStreams = defined(
            '\Shopware\Core\Content\Category\CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM',
        );
        $isProductStream = $supportsProductStreams &&
            $category->getProductAssignmentType() === CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM;
        if ($isProductStream && $category->getProductStreamId() !== null) {
            $filters = $this->productStreamBuilder->buildFilters(
                $category->getProductStreamId(),
                $salesChannelContext->getContext(),
            );

            $criteria->addFilter(...$filters);

            return $category->getProductStreamId();
        }

        $criteria->addFilter(
            new EqualsFilter('product.categoriesRo.id', $category->getId()),
        );

        return null;
    }

    private function fetchProductsById(
        Criteria $criteria,
        SalesChannelContext $salesChannelContext,
    ): EntitySearchResult {
        if (empty($criteria->getIds())) {
            return $this->createEmptySearchResult($criteria, $salesChannelContext->getContext());
        }

        return $this->fetchProducts($criteria, $salesChannelContext);
    }
}
