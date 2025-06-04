<?php

namespace Nosto\NostoIntegration\Decorator\Core\Content\Category\SalesChannel;

use Shopware\Core\Content\Category\SalesChannel\AbstractCategoryRoute;
use Shopware\Core\Content\Category\SalesChannel\CategoryRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class NoCacheCategoryRoute extends AbstractCategoryRoute
{
    private readonly AbstractCategoryRoute $decorated;
    public function __construct(
        AbstractCategoryRoute $decorated,
    ) {
        $this->decorated = $decorated;
    }

    public function getDecorated(): AbstractCategoryRoute
    {
        return $this->decorated;
    }

    public function load(string $navigationId, Request $request, SalesChannelContext $context): CategoryRouteResponse
    {
        return $this->getDecorated()->getDecorated()->load($navigationId, $request, $context);
    }
}
