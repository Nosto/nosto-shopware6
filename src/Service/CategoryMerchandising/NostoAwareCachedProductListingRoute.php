<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service\CategoryMerchandising;

use Shopware\Core\Content\Product\SalesChannel\Listing\{AbstractProductListingRoute, ProductListingRouteResponse};
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class NostoAwareCachedProductListingRoute extends AbstractProductListingRoute
{
    public function __construct(
        private readonly AbstractProductListingRoute $decoratedService,
        private readonly NostoCacheResolver $cacheResolver,
    ) {
    }

    public function getDecorated(): AbstractProductListingRoute
    {
        return $this->decoratedService;
    }

    public function load(
        string $categoryId,
        Request $request,
        SalesChannelContext $context,
        Criteria $criteria,
    ): ProductListingRouteResponse {
        if ($this->cacheResolver->isCachingAllowedNoRoute($context)) {
            /** Allow caching */
            return $this->decoratedService->load($categoryId, $request, $context, $criteria);
        }

        /** Bypass the caching */
        return $this->decoratedService->getDecorated()->load($categoryId, $request, $context, $criteria);
    }
}
