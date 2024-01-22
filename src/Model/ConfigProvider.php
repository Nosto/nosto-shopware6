<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model;

use Nosto\NostoIntegration\Enums\CategoryNamingOptions;
use Nosto\NostoIntegration\Enums\CrossSellingSyncOptions;
use Nosto\NostoIntegration\Enums\ProductIdentifierOptions;
use Nosto\NostoIntegration\Enums\RatingOptions;
use Nosto\NostoIntegration\Enums\StockFieldOptions;
use Nosto\NostoIntegration\Model\Config\NostoConfigService;

class ConfigProvider
{
    public function __construct(
        private readonly NostoConfigService $configService,
    ) {
    }

    public function isAccountEnabled($channelId = null, $languageId = null): bool
    {
        return $this->configService->getBool(NostoConfigService::ACCOUNT_ENABLED, $channelId, $languageId);
    }

    public function getAccountId($channelId = null, $languageId = null): string
    {
        return $this->configService->getString(NostoConfigService::ACCOUNT_ID, $channelId, $languageId);
    }

    public function getAccountName($channelId = null, $languageId = null): string
    {
        return $this->configService->getString(NostoConfigService::ACCOUNT_NAME, $channelId, $languageId);
    }

    public function getProductToken($channelId = null, $languageId = null): string
    {
        return $this->configService->getString(NostoConfigService::PRODUCT_TOKEN, $channelId, $languageId);
    }

    public function getEmailToken($channelId = null, $languageId = null): string
    {
        return $this->configService->getString(NostoConfigService::EMAIL_TOKEN, $channelId, $languageId);
    }

    public function getAppToken($channelId = null, $languageId = null): string
    {
        return $this->configService->getString(NostoConfigService::GRAPHQL_TOKEN, $channelId, $languageId);
    }

    public function getSearchToken($channelId = null, $languageId = null): string
    {
        return $this->configService->getString(NostoConfigService::SEARCH_TOKEN, $channelId, $languageId);
    }

    public function isSearchEnabled($channelId = null, $languageId = null): bool
    {
        return $this->configService->getBool(NostoConfigService::ENABLE_SEARCH, $channelId, $languageId);
    }

    public function isNavigationEnabled($channelId = null, $languageId = null): bool
    {
        return $this->configService->getBool(NostoConfigService::ENABLE_NAVIGATION, $channelId, $languageId);
    }

    public function shouldInitializeNostoAfterInteraction($channelId = null, $languageId = null): bool
    {
        return $this->configService->getBool(
            NostoConfigService::INITIALIZE_NOSTO_AFTER_INTERACTION,
            $channelId,
            $languageId,
        );
    }

    public function getDomainId($channelId = null, $languageId = null): ?string
    {
        $domainId = $this->configService->get(NostoConfigService::DOMAIN_ID, $channelId, $languageId);
        return is_string($domainId) ? $domainId : null;
    }

    public function getSelectedCustomFields($channelId = null, $languageId = null): array
    {
        $value = $this->configService->get(NostoConfigService::SELECTED_CUSTOM_FIELDS, $channelId, $languageId);
        return is_array($value) ? $value : [];
    }

    public function getTagFieldKey(int $tagNumber, $channelId = null, $languageId = null): array
    {
        $config = $this->configService->get(
            NostoConfigService::TAG_FIELD_TEMPLATE . $tagNumber,
            $channelId,
            $languageId,
        );
        if (is_string($config) && !empty($config)) {
            return [$config];
        } elseif (is_array($config)) {
            return $config;
        }
        return [];
    }

    public function getGoogleCategory($channelId = null, $languageId = null): ?string
    {
        return $this->configService->getString(NostoConfigService::GOOGLE_CATEGORY, $channelId, $languageId);
    }

    public function getProductIdentifier($channelId = null, $languageId = null): ProductIdentifierOptions
    {
        $value = $this->configService->get(NostoConfigService::PRODUCT_IDENTIFIER_FIELD, $channelId, $languageId);

        return ProductIdentifierOptions::tryFrom($value) ?? ProductIdentifierOptions::PRODUCT_ID;
    }

    public function getRatingReviews($channelId = null, $languageId = null): RatingOptions
    {
        $value = $this->configService->get(NostoConfigService::RATING_REVIEWS, $channelId, $languageId);

        return RatingOptions::tryFrom($value) ?? RatingOptions::SHOPWARE_RATINGS;
    }

    public function getStockField($channelId = null, $languageId = null): StockFieldOptions
    {
        $value = $this->configService->get(NostoConfigService::STOCK_FIELD, $channelId, $languageId);

        return StockFieldOptions::tryFrom($value) ?? StockFieldOptions::AVAILABLE_STOCK;
    }

    public function getCrossSellingSyncOption($channelId = null, $languageId = null): CrossSellingSyncOptions
    {
        $value = $this->configService->get(NostoConfigService::CROSS_SELLING_SYNC_FIELD, $channelId, $languageId);

        return CrossSellingSyncOptions::tryFrom($value) ?? CrossSellingSyncOptions::NO_SYNC;
    }

    public function getCategoryNamingOption($channelId = null, $languageId = null): CategoryNamingOptions
    {
        $value = $this->configService->get(NostoConfigService::CATEGORY_NAMING_FIELD, $channelId, $languageId);

        return CategoryNamingOptions::tryFrom($value) ?? CategoryNamingOptions::NO_ID;
    }

    public function getCategoryBlocklist($channelId = null, $languageId = null): array
    {
        $value = $this->configService->get(NostoConfigService::CATEGORY_BLOCKLIST, $channelId, $languageId);
        return is_array($value) ? $value : [];
    }

    public function isEnabledVariations($channelId = null, $languageId = null): bool
    {
        return $this->configService->getBool(NostoConfigService::ENABLE_VARIATIONS, $channelId, $languageId);
    }

    public function isEnabledProductProperties($channelId = null, $languageId = null): bool
    {
        return $this->configService->getBool(NostoConfigService::ENABLE_PRODUCT_PROPERTIES, $channelId, $languageId);
    }

    public function isEnabledAlternateImages($channelId = null, $languageId = null): bool
    {
        return $this->configService->getBool(NostoConfigService::ENABLE_ALTERNATE_IMAGES, $channelId, $languageId);
    }

    public function isEnabledInventoryLevels($channelId = null, $languageId = null): bool
    {
        return $this->configService->getBool(NostoConfigService::ENABLE_INVENTORY_LEVELS, $channelId, $languageId);
    }

    public function isEnabledCustomerDataToNosto($channelId = null, $languageId = null): bool
    {
        return $this->configService->getBool(
            NostoConfigService::ENABLE_CUSTOMER_DATA_TO_NOSTO,
            $channelId,
            $languageId,
        );
    }

    public function isEnabledSyncInactiveProducts($channelId = null, $languageId = null): bool
    {
        return $this->configService->getBool(
            NostoConfigService::ENABLE_SYNC_INACTIVE_PRODUCTS,
            $channelId,
            $languageId,
        );
    }

    public function isEnabledProductPublishedDateTagging($channelId = null, $languageId = null): bool
    {
        return $this->configService->getBool(
            NostoConfigService::ENABLE_PRODUCT_PUBLISHED_DATE_TAGGING,
            $channelId,
            $languageId,
        );
    }

    public function isEnabledReloadRecommendationsAfterAdding($channelId = null, $languageId = null): bool
    {
        return $this->configService->getBool(
            NostoConfigService::ENABLE_RELOAD_RECOMMENDATIONS_AFTER_ADDING,
            $channelId,
            $languageId,
        );
    }

    public function isEnabledProductLabellingSync($channelId = null, $languageId = null): bool
    {
        return $this->configService->getBool(
            NostoConfigService::ENABLE_PRODUCT_LABELLING_SYNC,
            $channelId,
            $languageId,
        );
    }

    public function isDailyProductSyncEnabled($channelId = null, $languageId = null): bool
    {
        return $this->configService->getBool(NostoConfigService::DAILY_PRODUCT_SYNC_ENABLED, $channelId, $languageId);
    }

    public function getDailyProductSyncTime($channelId = null, $languageId = null): ?string
    {
        return $this->configService->get(NostoConfigService::DAILY_PRODUCT_SYNC_TIME, $channelId, $languageId);
    }

    public function isOldJobCleanupEnabled($channelId = null, $languageId = null): bool
    {
        return $this->configService->getBool(NostoConfigService::OLD_JOB_CLEANUP_ENABLED, $channelId, $languageId);
    }

    public function getOldJobCleanupPeriod($channelId = null, $languageId = null): ?string
    {
        return $this->configService->get(NostoConfigService::OLD_JOB_CLEANUP_PERIOD, $channelId, $languageId);
    }
}
