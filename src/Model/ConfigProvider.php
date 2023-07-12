<?php declare(strict_types=1);

namespace Od\NostoIntegration\Model;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigProvider
{
    private SystemConfigService $systemConfig;
    protected string $pathPrefix = 'overdose_nosto.';

    public const ENABLE_VARIATIONS = 'config.variations';
    public const ENABLE_PRODUCT_PROPERTIES = 'config.productProperties';
    public const ENABLE_ALTERNATE_IMAGES = 'config.alternateImages';
    public const ENABLE_INVENTORY_LEVELS = 'config.inventory';
    public const ENABLE_SYNC_INACTIVE_PRODUCTS = 'config.syncInactiveProducts';
    public const ENABLE_PRODUCT_PUBLISHED_DATE_TAGGING = 'config.productPublishedDateTagging';
    public const ENABLE_RELOAD_RECOMMENDATIONS_AFTER_ADDING = 'config.reloadRecommendations';
    public const DAILY_PRODUCT_SYNC_ENABLED = 'config.dailySynchronization';
    public const DAILY_PRODUCT_SYNC_TIME = 'config.dailySynchronizationTime';
    public const STOCK_FIELD = 'config.stockField';
    public const PRODUCT_IDENTIFIER_FIELD = 'config.productIdentifier';
    public const CROSS_SELLING_SYNC_FIELD = 'config.crossSellingSync';
    public const ENABLE_MERCH = 'config.enableMerch';
    public const ENABLE_NOT_LOGGED_IN_CACHE = 'config.notLoggedInCache';
    public const DOMAIN_ID = 'config.domain';
    public const ACCOUNT_ENABLED = 'config.isEnabled';
    public const ACCOUNT_ID = 'config.accountID';
    public const ACCOUNT_NAME = 'config.accountName';
    public const PRODUCT_TOKEN = 'config.productToken';
    public const EMAIL_TOKEN = 'config.emailToken';
    public const GRAPHQL_TOKEN = 'config.appToken';
    public const TAG_FIELD_TEMPLATE = 'config.tag';
    public const SELECTED_CUSTOM_FIELDS = 'config.selectedCustomFields';
    public const ENABLE_PRODUCT_LABELLING_SYNC = 'config.enableLabelling';

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
