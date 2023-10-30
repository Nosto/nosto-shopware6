<?php

namespace Nosto\NostoIntegration\Utils;

use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Struct\NostoService;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class SearchHelper
{
    public static function shouldHandleRequest(
        Context $context,
        ConfigProvider $configProvider,
        bool $isNavigationPage = false
    ): bool {
        /** @var NostoService $nostoService */
        $nostoService = $context->getExtension('nostoService');
        if ($nostoService && $nostoService->getEnabled()) {
            return $nostoService->getEnabled();
        }

        $nostoService = new NostoService();
        $context->addExtension('nostoService', $nostoService);

        $searchApiToken = $configProvider->getSearchToken();
        $accountId = $configProvider->getAccountId();
        if (!$searchApiToken || trim($searchApiToken) === '' || !$accountId || trim($accountId) === '') {
            return $nostoService->disable();
        }

        if (
            !$configProvider->isSearchEnabled() ||
            ($isNavigationPage && !$configProvider->isNavigationEnabled())
        ) {
            return $nostoService->disable();
        }

        return $nostoService->enable();
    }

    public static function isSearchPage(Request $request): bool
    {
        return str_contains($request->getRequestUri(), '/search');
    }

    public static function isNavigationPage(Request $request): bool
    {
        return $request->attributes->has('navigationId');
    }

    public static function isNostoEnabled(SalesChannelContext $context): bool
    {
        /** @var NostoService $nostoService */
        $nostoService = $context->getContext()->getExtension('nostoService');

        return $nostoService && $nostoService->getEnabled();
    }

    public static function disableNostoWhenEnabled(SalesChannelContext $context): void
    {
        if (!static::isNostoEnabled($context)) {
            return;
        }

        if (!$context->getContext()->hasExtension('nostoService')) {
            return;
        }

        /** @var NostoService $nostoService */
        $nostoService = $context->getContext()->getExtension('nostoService');
        $nostoService->disable();
    }
}