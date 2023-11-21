<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service\CategoryMerchandising;

use Shopware\Core\Content\Category\SalesChannel\{AbstractCategoryRoute, CategoryRouteResponse};
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class NostoAwareCachedCategoryRoute extends AbstractCategoryRoute
{
    private AbstractCategoryRoute $decoratedService;

    private NostoCacheResolver $cacheResolver;

    public function __construct(AbstractCategoryRoute $cachedCategoryRoute, NostoCacheResolver $cacheResolver)
    {
        $this->decoratedService = $cachedCategoryRoute;
        $this->cacheResolver = $cacheResolver;
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
