<?php declare(strict_types=1);

namespace Od\NostoIntegration\Service\CategoryMerchandising;

use Od\NostoIntegration\Model\ConfigProvider;
use Od\NostoIntegration\Model\Nosto\Account\Provider;
use Shopware\Core\Content\Product\SalesChannel\Listing\{AbstractProductListingRoute, ProductListingRouteResponse};
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class NostoAwareCachedProductListingRoute extends AbstractProductListingRoute
{
    private AbstractProductListingRoute $decoratedService;
    private Provider $accountProvider;
    private ConfigProvider $configProvider;

    public function __construct(
        AbstractProductListingRoute $cachedProductListingRoute,
        Provider $accountProvider,
        ConfigProvider $configProvider
    ) {
        $this->decoratedService = $cachedProductListingRoute;
        $this->accountProvider = $accountProvider;
        $this->configProvider = $configProvider;
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
        $isLoggedIn = $channelContext->getCustomer() !== null;
        $isMerchEnabled = $this->configProvider->isMerchEnabled($channelContext->getSalesChannelId());
        $nostoAccount = $this->accountProvider->get($channelContext->getSalesChannelId());

        if ($nostoAccount && $isMerchEnabled && $isLoggedIn) {
            /** Bypass the caching */
            return $this->decoratedService->getDecorated()->load($categoryId, $request, $channelContext, $criteria);
        }

        /** Allow caching */
        return $this->decoratedService->load($categoryId, $request, $channelContext, $criteria);
    }
}
