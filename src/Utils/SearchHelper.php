<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Utils;

use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Struct\Config;
use Nosto\NostoIntegration\Struct\NostoService;
use Nosto\NostoIntegration\Struct\PageInformation;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class SearchHelper
{
    public static function shouldHandleRequest(
        SalesChannelContext $context,
        ConfigProvider $configProvider,
        bool $isNavigationPage = false,
    ): bool {
        /** @var NostoService $nostoService */
        $nostoService = $context->getContext()->getExtension('nostoService');
        if ($nostoService?->getEnabled()) {
            return $nostoService->getEnabled();
        }

        $nostoService = new NostoService();
        $context->getContext()->addExtension('nostoService', $nostoService);

        $channelId = $context->getSalesChannelId();
        $languageId = $context->getLanguageId();
        $searchApiToken = $configProvider->getSearchToken($channelId, $languageId);
        $accountId = $configProvider->getAccountId($channelId, $languageId);
        if (!$searchApiToken || trim($searchApiToken) === '' || !$accountId || trim($accountId) === '') {
            return $nostoService->disable();
        }

        if (
            (!$isNavigationPage && !$configProvider->isSearchEnabled($channelId, $languageId)) ||
            ($isNavigationPage && !$configProvider->isNavigationEnabled($channelId, $languageId))
        ) {
            return $nostoService->disable();
        }

        if (!$context->getContext()->hasExtension('nostoConfig')) {
            $nostoConfig = new Config($configProvider->toArray($channelId, $languageId));
            $context->getContext()->addExtension('nostoConfig', $nostoConfig);
        }

        if (!$context->getContext()->hasExtension('nostoPageInformation')) {
            $nostoPageInformation = new PageInformation(!$isNavigationPage, $isNavigationPage);
            $context->getContext()->addExtension('nostoPageInformation', $nostoPageInformation);
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
