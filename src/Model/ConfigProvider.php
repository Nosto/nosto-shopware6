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
    public const ENABLE_MERCH = 'settings.enableMerch';
    public const ENABLE_NOT_LOGGED_IN_CACHE = 'settings.notLoggedInCache';
    public const ACCOUNT_ID = 'settings.accounts.accountID';
    public const ACCOUNT_NAME = 'settings.accounts.accountName';
    public const PRODUCT_TOKEN = 'settings.accounts.productToken';
    public const EMAIL_TOKEN = 'settings.accounts.emailToken';
    public const GRAPHQL_TOKEN = 'settings.accounts.appToken';
    public const TAG_FIELD_TEMPLATE = 'settings.tag';

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

    public function getTagFieldKey(int $tagNumber, $channelId = null): string
    {
        return $this->systemConfig->getString($this->path(self::TAG_FIELD_TEMPLATE) . $tagNumber, $channelId);
    }
}
