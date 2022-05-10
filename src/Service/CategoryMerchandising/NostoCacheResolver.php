<?php declare(strict_types=1);

namespace Od\NostoIntegration\Service\CategoryMerchandising;

use Od\NostoIntegration\Model\ConfigProvider;
use Symfony\Component\HttpFoundation\RequestStack;

class NostoCacheResolver
{
    private RequestStack $requestStack;
    private ConfigProvider $configProvider;

    public function __construct(
        RequestStack $requestStack,
        ConfigProvider $configProvider
    ) {
        $this->requestStack = $requestStack;
        $this->configProvider = $configProvider;
    }

    public function isCachingAllowed(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return false;
        }
        $activeRoute = $request->attributes->get('_route');
        $enabledCache = $this->configProvider->isEnabledNotLoggedInCache();
        $customer = $request->attributes->get('sw-sales-channel-context')->getCustomer();
        $isCategoryRoute = $activeRoute === 'frontend.navigation.page';

        return (!$customer && $isCategoryRoute && !$enabledCache) || ($customer && $isCategoryRoute);
    }
}