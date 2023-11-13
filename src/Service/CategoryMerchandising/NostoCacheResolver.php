<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service\CategoryMerchandising;

use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Account\Provider;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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
    ) {
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
     */
    public function isCachingAllowed(?SalesChannelContext $channelContext = null): bool
    {
        if (!$request = $this->requestStack->getCurrentRequest()) {
            return true;
        }

        $isCategoryRoute = $request->attributes->get('_route') === 'frontend.navigation.page';
        $isCachingAllowed = $this->isCachingAllowedNoRoute($channelContext);

        if (!$isCachingAllowed && !$isCategoryRoute) {
            $isCachingAllowed = true;
        }

        return $isCachingAllowed;
    }

    public function isCachingAllowedNoRoute(?SalesChannelContext $channelContext = null): bool
    {
        $isCachingAllowed = true;

        if (!$request = $this->requestStack->getCurrentRequest()) {
            return $isCachingAllowed;
        }

        /** @var SalesChannelContext $channelContext */
        $channelContext = $channelContext ?? $request->attributes->get('sw-sales-channel-context');
        if (!$channelContext) {
            return $isCachingAllowed;
        }

        if ($this->getBasicCachingAllowance($channelContext)) {
            $channelId = $channelContext->getSalesChannelId();
            $languageId = $channelContext->getLanguageId();
            $isLoggedIn = $channelContext->getCustomer() !== null;
            $isEnabledNotLoggedIdCache = $this->configProvider->isEnabledNotLoggedInCache($channelId, $languageId);

            $isCachingAllowed = $isLoggedIn === true ? false : $isEnabledNotLoggedIdCache;
        }

        return $isCachingAllowed;
    }

    private function getBasicCachingAllowance(SalesChannelContext $channelContext): bool
    {
        $channelId = $channelContext->getSalesChannelId();
        $languageId = $channelContext->getLanguageId();
        $isMerchEnabled = $this->configProvider->isMerchEnabled($channelId, $languageId);
        $isNostoAccountExists = $this->accountProvider->get(
            $channelContext->getContext(),
            $channelId,
            $languageId,
        ) !== null;

        return $isMerchEnabled && $isNostoAccountExists;
    }
}
