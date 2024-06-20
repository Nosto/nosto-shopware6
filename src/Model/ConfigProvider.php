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

    public function isAccountEnabled(?string $channelId = null, ?string $languageId = null): bool
    {
        return $this->configService->getBool(NostoConfigService::ACCOUNT_ENABLED, $channelId, $languageId);
    }

    public function getAccountId(?string $channelId = null, ?string $languageId = null): string
    {
        return $this->configService->getString(NostoConfigService::ACCOUNT_ID, $channelId, $languageId);
    }

    public function getAccountName(?string $channelId = null, ?string $languageId = null): string
    {
        return $this->configService->getString(NostoConfigService::ACCOUNT_NAME, $channelId, $languageId);
    }

    public function getProductToken(?string $channelId = null, ?string $languageId = null): string
    {
        return $this->configService->getString(NostoConfigService::PRODUCT_TOKEN, $channelId, $languageId);
    }

    public function getEmailToken(?string $channelId = null, ?string $languageId = null): string
    {
        return $this->configService->getString(NostoConfigService::EMAIL_TOKEN, $channelId, $languageId);
    }

    public function getAppToken(?string $channelId = null, ?string $languageId = null): string
    {
        return $this->configService->getString(NostoConfigService::GRAPHQL_TOKEN, $channelId, $languageId);
    }

    public function getSearchToken(?string $channelId = null, ?string $languageId = null): string
    {
        return $this->configService->getString(NostoConfigService::SEARCH_TOKEN, $channelId, $languageId);
    }

    public function isSearchEnabled(?string $channelId = null, ?string $languageId = null): bool
    {
        return $this->configService->getBool(NostoConfigService::ENABLE_SEARCH, $channelId, $languageId);
    }

    public function isNavigationEnabled(?string $channelId = null, ?string $languageId = null): bool
    {
        return $this->configService->getBool(NostoConfigService::ENABLE_NAVIGATION, $channelId, $languageId);
    }

    public function shouldInitializeNostoAfterInteraction(?string $channelId = null, ?string $languageId = null): bool
    {
        return $this->configService->getBool(
            NostoConfigService::INITIALIZE_NOSTO_AFTER_INTERACTION,
            $channelId,
            $languageId,
        );
    }

    public function getDomainId(?string $channelId = null, ?string $languageId = null): ?string
    {
        $domainId = $this->configService->get(NostoConfigService::DOMAIN_ID, $channelId, $languageId);
        return is_string($domainId) ? $domainId : null;
    }

    /**
     * @return string[]
     */
    public function getSelectedCustomFields(?string $channelId = null, ?string $languageId = null): array
    {
        $value = $this->configService->get(NostoConfigService::SELECTED_CUSTOM_FIELDS, $channelId, $languageId);
        return is_array($value) ? $value : [];
    }

    /**
     * @return string[]
     */
    public function getTagFieldKey(int $tagNumber, ?string $channelId = null, ?string $languageId = null): array
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

    public function getGoogleCategory(?string $channelId = null, ?string $languageId = null): ?string
    {
        return $this->configService->getString(NostoConfigService::GOOGLE_CATEGORY, $channelId, $languageId);
    }

    public function getProductIdentifier(
        ?string $channelId = null,
        ?string $languageId = null,
    ): ProductIdentifierOptions {
        $value = $this->configService->get(NostoConfigService::PRODUCT_IDENTIFIER_FIELD, $channelId, $languageId);

        return ProductIdentifierOptions::tryFrom($value) ?? ProductIdentifierOptions::PRODUCT_ID;
    }

    public function getRatingReviews(?string $channelId = null, ?string $languageId = null): RatingOptions
    {
        $value = $this->configService->get(NostoConfigService::RATING_REVIEWS, $channelId, $languageId);

        return RatingOptions::tryFrom($value) ?? RatingOptions::SHOPWARE_RATINGS;
    }

    public function getStockField(?string $channelId = null, ?string $languageId = null): StockFieldOptions
    {
        $value = $this->configService->get(NostoConfigService::STOCK_FIELD, $channelId, $languageId);

        return StockFieldOptions::tryFrom($value) ?? StockFieldOptions::AVAILABLE_STOCK;
    }

    public function getCrossSellingSyncOption(
        ?string $channelId = null,
        ?string $languageId = null,
    ): CrossSellingSyncOptions {
        $value = $this->configService->get(NostoConfigService::CROSS_SELLING_SYNC_FIELD, $channelId, $languageId);

        return CrossSellingSyncOptions::tryFrom($value) ?? CrossSellingSyncOptions::NO_SYNC;
    }

    public function getCategoryNamingOption(
        ?string $channelId = null,
        ?string $languageId = null,
    ): CategoryNamingOptions {
        $value = $this->configService->get(NostoConfigService::CATEGORY_NAMING_FIELD, $channelId, $languageId);

        return CategoryNamingOptions::tryFrom($value) ?? CategoryNamingOptions::NO_ID;
    }

    /**
     * @return string[]
     */
    public function getCategoryBlocklist(?string $channelId = null, ?string $languageId = null): array
    {
        $value = $this->configService->get(NostoConfigService::CATEGORY_BLOCKLIST, $channelId, $languageId);
        return is_array($value) ? $value : [];
    }

    public function isEnabledVariations(?string $channelId = null, ?string $languageId = null): bool
    {
        return $this->configService->getBool(NostoConfigService::ENABLE_VARIATIONS, $channelId, $languageId);
    }

    public function isEnabledProductProperties(?string $channelId = null, ?string $languageId = null): bool
    {
        return $this->configService->getBool(NostoConfigService::ENABLE_PRODUCT_PROPERTIES, $channelId, $languageId);
    }

    public function isEnabledAlternateImages(?string $channelId = null, ?string $languageId = null): bool
    {
        return $this->configService->getBool(NostoConfigService::ENABLE_ALTERNATE_IMAGES, $channelId, $languageId);
    }

    public function isEnabledInventoryLevels(?string $channelId = null, ?string $languageId = null): bool
    {
        return $this->configService->getBool(NostoConfigService::ENABLE_INVENTORY_LEVELS, $channelId, $languageId);
    }

    public function isEnabledCustomerDataToNosto(?string $channelId = null, ?string $languageId = null): bool
    {
        return $this->configService->getBool(
            NostoConfigService::ENABLE_CUSTOMER_DATA_TO_NOSTO,
            $channelId,
            $languageId,
        );
    }

    public function isEnabledSyncInactiveProducts(?string $channelId = null, ?string $languageId = null): bool
    {
        return $this->configService->getBool(
            NostoConfigService::ENABLE_SYNC_INACTIVE_PRODUCTS,
            $channelId,
            $languageId,
        );
    }

    public function isEnabledProductPublishedDateTagging(?string $channelId = null, ?string $languageId = null): bool
    {
        return $this->configService->getBool(
            NostoConfigService::ENABLE_PRODUCT_PUBLISHED_DATE_TAGGING,
            $channelId,
            $languageId,
        );
    }

    public function isEnabledReloadRecommendationsAfterAdding(
        ?string $channelId = null,
        ?string $languageId = null,
    ): bool {
        return $this->configService->getBool(
            NostoConfigService::ENABLE_RELOAD_RECOMMENDATIONS_AFTER_ADDING,
            $channelId,
            $languageId,
        );
    }

    public function isEnabledProductLabellingSync(?string $channelId = null, ?string $languageId = null): bool
    {
        return $this->configService->getBool(
            NostoConfigService::ENABLE_PRODUCT_LABELLING_SYNC,
            $channelId,
            $languageId,
        );
    }

    public function isDailyProductSyncEnabled(?string $channelId = null, ?string $languageId = null): bool
    {
        return $this->configService->getBool(NostoConfigService::DAILY_PRODUCT_SYNC_ENABLED, $channelId, $languageId);
    }

    public function getDailyProductSyncTime(?string $channelId = null, ?string $languageId = null): ?string
    {
        return $this->configService->get(NostoConfigService::DAILY_PRODUCT_SYNC_TIME, $channelId, $languageId);
    }

    public function isOldJobCleanupEnabled(?string $channelId = null, ?string $languageId = null): bool
    {
        return $this->configService->getBool(NostoConfigService::OLD_JOB_CLEANUP_ENABLED, $channelId, $languageId);
    }

    public function getOldJobCleanupPeriod(?string $channelId = null, ?string $languageId = null): ?int
    {
        return $this->configService->getInt(NostoConfigService::OLD_JOB_CLEANUP_PERIOD, $channelId, $languageId);
    }

    public function isOldNostoDataCleanupEnabled($channelId = null, $languageId = null): bool
    {
        return $this->configService->getBool(
            NostoConfigService::OLD_NOSTO_DATA_CLEANUP_ENABLED,
            $channelId,
            $languageId
        );
    }

    public function getOldNostoDataCleanupPeriod($channelId = null, $languageId = null): ?int
    {
        return $this->configService->getInt(NostoConfigService::OLD_NOSTO_DATA_CLEANUP_PERIOD, $channelId, $languageId);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(?string $channelId = null, ?string $languageId = null): array
    {
        return $this->configService->getConfigWithInheritance($channelId, $languageId);
    }
}
