<?php declare(strict_types=1);

namespace Od\NostoIntegration\Service\CategoryMerchandising;

use Shopware\Core\Content\Category\SalesChannel\{AbstractCategoryRoute, CategoryRouteResponse};
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class NostoAwareCachedCategoryRoute extends AbstractCategoryRoute
{
    private AbstractCategoryRoute $decoratedService;

    public function __construct(AbstractCategoryRoute $cachedCategoryRoute)
    {
        $this->decoratedService = $cachedCategoryRoute;
    }

    public function load(
        string $navigationId,
        Request $request,
        SalesChannelContext $context
    ): CategoryRouteResponse {
        return $this->getDecorated()->load($navigationId, $request, $context);
    }

    public function getDecorated(): AbstractCategoryRoute
    {
        return $this->decoratedService;
    }
}