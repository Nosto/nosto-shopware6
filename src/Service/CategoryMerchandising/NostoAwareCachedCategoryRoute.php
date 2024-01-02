<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service\CategoryMerchandising;

use Shopware\Core\Content\Category\SalesChannel\{AbstractCategoryRoute, CategoryRouteResponse};
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class NostoAwareCachedCategoryRoute extends AbstractCategoryRoute
{
    public function __construct(
        private readonly AbstractCategoryRoute $decoratedService,
        private readonly NostoCacheResolver $cacheResolver,
    ) {
    }

    public function load(
        string $navigationId,
        Request $request,
        SalesChannelContext $context,
    ): CategoryRouteResponse {
        if ($this->cacheResolver->isCachingAllowedNoRoute($context)) {
            /** Allow caching */
            return $this->decoratedService->load($navigationId, $request, $context);
        }

        /** Bypass the caching */
        return $this->getDecorated()->load($navigationId, $request, $context);
    }

    public function getDecorated(): AbstractCategoryRoute
    {
        return $this->decoratedService;
    }
}
