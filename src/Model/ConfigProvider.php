<?php declare(strict_types=1);

namespace Od\NostoIntegration\Model;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigProvider
{
    private SystemConfigService $systemConfig;
    protected string $pathPrefix = 'NostoIntegration.';

    public const ENABLE_VARIATIONS = 'settings.flags.variations';
    public const ENABLE_PRODUCT_PROPERTIES = 'settings.flags.productProperties';
    public const ENABLE_ALTERNATE_IMAGES = 'settings.flags.alternateImages';
    public const ENABLE_INVENTORY_LEVELS = 'settings.flags.inventory';
    public const ENABLE_SYNC_INACTIVE_PRODUCTS = 'settings.flags.syncInactiveProducts';
    public const ENABLE_PRODUCT_PUBLISHED_DATE_TAGGING = 'settings.flags.productPublishedDateTagging';
    public const ENABLE_RELOAD_RECOMMENDATIONS_AFTER_ADDING = 'settings.flags.reloadRecommendations';
    public const DAILY_PRODUCT_SYNC_ENABLED = 'settings.flags.dailySynchronization';
    public const DAILY_PRODUCT_SYNC_TIME = 'settings.flags.dailySynchronizationTime';
    public const STOCK_FIELD = 'settings.flags.stockField';
    public const PRODUCT_IDENTIFIER_FIELD = 'settings.flags.productIdentifier';
    public const CROSS_SELLING_SYNC_FIELD = 'settings.flags.crossSellingSync';
    public const ENABLE_MERCH = 'settings.enableMerch';
    public const ENABLE_NOT_LOGGED_IN_CACHE = 'settings.notLoggedInCache';
    public const DOMAIN_ID = 'settings.domain';
    public const ACCOUNT_ENABLED = 'settings.accounts.isEnabled';
    public const ACCOUNT_ID = 'settings.accounts.accountID';
    public const ACCOUNT_NAME = 'settings.accounts.accountName';
    public const PRODUCT_TOKEN = 'settings.accounts.productToken';
    public const EMAIL_TOKEN = 'settings.accounts.emailToken';
    public const GRAPHQL_TOKEN = 'settings.accounts.appToken';
    public const TAG_FIELD_TEMPLATE = 'settings.tag';
    public const SELECTED_CUSTOM_FIELDS = 'settings.selectedCustomFields';
    public const ENABLE_PRODUCT_LABELLING_SYNC = 'settings.flags.enableLabelling';

    public function __construct(SystemConfigService $systemConfig)
    {
        $this->systemConfig = $systemConfig;
    }

    private function path(string $postfix): string
    {
        return $this->pathPrefix . $postfix;
    }

    public function isEnabledVariations($channelId = null): bool
    {
        return $this->systemConfig->getBool($this->path(self::ENABLE_VARIATIONS), $channelId);
    }

    public function isEnabledProductProperties($channelId = null): bool
    {
        return $this->systemConfig->getBool($this->path(self::ENABLE_PRODUCT_PROPERTIES), $channelId);
    }

    public function isEnabledAlternateImages($channelId = null): bool
    {
        return $this->systemConfig->getBool($this->path(self::ENABLE_ALTERNATE_IMAGES), $channelId);
    }

    public function isEnabledInventoryLevels($channelId = null): bool
    {
        return $this->systemConfig->getBool($this->path(self::ENABLE_INVENTORY_LEVELS), $channelId);
    }

    public function isEnabledSyncInactiveProducts($channelId = null): bool
    {
        return $this->systemConfig->getBool($this->path(self::ENABLE_SYNC_INACTIVE_PRODUCTS), $channelId);
    }

    public function isEnabledProductPublishedDateTagging($channelId = null): bool
    {
        return $this->systemConfig->getBool($this->path(self::ENABLE_PRODUCT_PUBLISHED_DATE_TAGGING), $channelId);
    }

    public function isEnabledReloadRecommendationsAfterAdding($channelId = null): bool
    {
        return $this->systemConfig->getBool($this->path(self::ENABLE_RELOAD_RECOMMENDATIONS_AFTER_ADDING), $channelId);
    }

    public function isMerchEnabled($channelId = null): bool
    {
        return $this->systemConfig->getBool($this->path(self::ENABLE_MERCH), $channelId);
    }

    public function isEnabledNotLoggedInCache($channelId = null): bool
    {
        return $this->systemConfig->getBool($this->path(self::ENABLE_NOT_LOGGED_IN_CACHE), $channelId);
    }

    public function getDomainId($channelId = null): ?string
    {
        $domainId = $this->systemConfig->get($this->path(self::DOMAIN_ID), $channelId);
        return is_string($domainId) ? $domainId : null;
    }

    public function isDailyProductSyncEnabled($channelId = null): bool
    {
        return $this->systemConfig->getBool($this->path(self::DAILY_PRODUCT_SYNC_ENABLED), $channelId);
    }

    public function isAccountEnabled($channelId = null): bool
    {
        return $this->systemConfig->getBool($this->path(self::ACCOUNT_ENABLED), $channelId);
    }

    public function getAccountId($channelId = null): string
    {
        return $this->systemConfig->getString($this->path(self::ACCOUNT_ID), $channelId);
    }

    public function getAccountName($channelId = null): string
    {
        return $this->systemConfig->getString($this->path(self::ACCOUNT_NAME), $channelId);
    }

    public function getProductToken($channelId = null): string
    {
        return $this->systemConfig->getString($this->path(self::PRODUCT_TOKEN), $channelId);
    }

    public function getEmailToken($channelId = null): string
    {
        return $this->systemConfig->getString($this->path(self::EMAIL_TOKEN), $channelId);
    }

    public function getAppToken($channelId = null): string
    {
        return $this->systemConfig->getString($this->path(self::GRAPHQL_TOKEN), $channelId);
    }

    public function getTagFieldKey(int $tagNumber, $channelId = null): array
    {
        $config = $this->systemConfig->get($this->path(self::TAG_FIELD_TEMPLATE) . $tagNumber, $channelId);
        if (is_string($config) && !empty($config)) {
            return [$config];
        } elseif (is_array($config)) {
            return $config;
        }
        return [];
    }

    public function getDailyProductSyncTime($channelId = null): ?string
    {
        return $this->systemConfig->get($this->path(self::DAILY_PRODUCT_SYNC_TIME), $channelId);
    }

    public function getSelectedCustomFields($channelId = null): array
    {
        $value = $this->systemConfig->get($this->path(self::SELECTED_CUSTOM_FIELDS), $channelId);
        return is_array($value) ? $value : [];
    }

    public function getStockField($channelId = null): ?string
    {
        return $this->systemConfig->getString($this->path(self::STOCK_FIELD), $channelId);
    }

    public function getCrossSellingSyncOption($channelId = null): string
    {
        $value = $this->systemConfig->get($this->path(self::CROSS_SELLING_SYNC_FIELD), $channelId);
        return is_string($value) ? $value : 'no-sync';
    }

    public function isEnabledProductLabellingSync($channelId = null): bool {
        return $this->systemConfig->getBool($this->path(self::ENABLE_PRODUCT_LABELLING_SYNC), $channelId);
    }

    public function getProductIdentifier($channelId = null): string
    {
        $value = $this->systemConfig->get($this->path(self::PRODUCT_IDENTIFIER_FIELD), $channelId);
        return is_string($value) ? $value : 'product-id';
    }
}
