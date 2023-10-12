<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service\CategoryMerchandising;

use Shopware\Core\Content\Product\SalesChannel\Listing\{AbstractProductListingRoute, ProductListingRouteResponse};
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class NostoAwareCachedProductListingRoute extends AbstractProductListingRoute
{
    private AbstractProductListingRoute $decoratedService;

    private NostoCacheResolver $cacheResolver;

    public function __construct(
        AbstractProductListingRoute $cachedProductListingRoute,
        NostoCacheResolver $cacheResolver
    ) {
        $this->decoratedService = $cachedProductListingRoute;
        $this->cacheResolver = $cacheResolver;
    }

    public function getDecorated(): AbstractProductListingRoute
    {
        return $this->decoratedService;
    }

    public function load(
        string $categoryId,
        Request $request,
        SalesChannelContext $channelContext,
        Criteria $criteria
    ): ProductListingRouteResponse {
        if ($this->cacheResolver->isCachingAllowedNoRoute($channelContext)) {
            /** Allow caching */
            return $this->decoratedService->load($categoryId, $request, $channelContext, $criteria);
        }

        /** Bypass the caching */
        return $this->decoratedService->getDecorated()->load($categoryId, $request, $channelContext, $criteria);
    }
}
