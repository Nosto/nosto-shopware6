<?php declare(strict_types=1);

namespace Od\NostoIntegration\Service\CategoryMerchandising;

use Od\NostoIntegration\Model\ConfigProvider;
use Od\NostoIntegration\Model\Nosto\Account\Provider;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class NostoCacheResolver
{
    private RequestStack $requestStack;
    private ConfigProvider $configProvider;
    private Provider $accountProvider;

    public function __construct(
        RequestStack $requestStack,
        ConfigProvider $configProvider,
        Provider $accountProvider
    )
    {
        $this->requestStack = $requestStack;
        $this->configProvider = $configProvider;
        $this->accountProvider = $accountProvider;
    }

    /**
     * The HTTP caching is allowed by default.
     * Caching is NOT allowed when:
     *
     *  1. Current page route is PLP;  (basic)
     *  2. Merch is enabled;           (basic)
     *  3. Nosto account is set up;    (basic)
     *  4. User is logged in.
     *
     * <or>
     *
     *  1. Current page route is PLP;  (basic)
     *  2. Merch is enabled;           (basic)
     *  3. Nosto account is set up;    (basic)
     *  4. User is NOT logged in;
     *  5. Not Logged In cache is NOT enabled in plugin config.
     *
     * @return bool
     */
    public function isCachingAllowed(): bool
    {
        $isCachingAllowed = true;

        if (!$request = $this->requestStack->getCurrentRequest()) {
            return $isCachingAllowed;
        }

        /** @var SalesChannelContext $channelContext */
        if (!$channelContext = $request->attributes->get('sw-sales-channel-context')) {
            return $isCachingAllowed;
        }

        if ($this->getBasicCachingAllowance($request, $channelContext)) {
            $channelId = $channelContext->getSalesChannelId();
            $isLoggedIn = $channelContext->getCustomer() !== null;
            $isEnabledNotLoggedIdCache = $this->configProvider->isEnabledNotLoggedInCache($channelId);

            $isCachingAllowed = $isLoggedIn === true ? false : $isEnabledNotLoggedIdCache;
        }

        return $isCachingAllowed;
    }

    private function getBasicCachingAllowance(Request $request, SalesChannelContext $channelContext): bool
    {
        $isCategoryRoute = $request->attributes->get('_route') === 'frontend.navigation.page';
        $isMerchEnabled = $this->configProvider->isMerchEnabled($channelContext->getSalesChannelId());
        $isNostoAccountExists = $this->accountProvider->get($channelContext->getSalesChannelId()) !== null;

        return $isCategoryRoute && $isMerchEnabled && $isNostoAccountExists;
    }
}
