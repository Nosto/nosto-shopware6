<?php declare(strict_types=1);

namespace Od\NostoIntegration\Model;

use Od\Base\Model\AbstractConfigProvider;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigProvider extends AbstractConfigProvider
{
    private SystemConfigService $systemConfig;
    protected string $pathPrefix = 'NostoIntegration.';

    public const ENABLE_VARIATIONS = 'feature.flags.variations';
    public const ENABLE_PRODUCT_PROPERTIES = 'feature.flags.productProperties';
    public const ENABLE_ALTERNATE_IMAGES = 'feature.flags.alternateImages';
    public const ENABLE_INVENTORY_LEVELS = 'feature.flags.inventory';
    public const ENABLE_SEND_CUSTOMER_DATA_TO_NOSTO = 'feature.flags.customerDataToNosto';
    public const ENABLE_SYNC_INACTIVE_PRODUCTS = 'feature.flags.syncInactiveProducts';
    public const ENABLE_PRODUCT_PUBLISHED_DATE_TAGGING = 'feature.flags.productPublishedDateTagging';
    public const ENABLE_RELOAD_RECOMMENDATIONS_AFTER_ADDING = 'feature.flags.reloadRecommendations';
    public const ACCOUNT_ID = 'settings.accounts.accountID';
    public const PRODUCT_TOKEN = 'settings.accounts.productToken';
    public const EMAIL_TOKEN = 'settings.accounts.emailToken';
    public const APP_TOKEN = 'settings.accounts.appToken';
    public const TAG_FIELD_TEMPLATE = 'settings.tag';

    public function __construct(SystemConfigService $systemConfig)
    {
        parent::__construct($systemConfig);
        $this->systemConfig = $systemConfig;
    }

    public function isEnabledVariations($channelId = null): bool
    {
        return $this->getBool(self::ENABLE_VARIATIONS, $channelId);
    }

    public function isEnabledProductProperties($channelId = null): bool
    {
        return $this->getBool(self::ENABLE_PRODUCT_PROPERTIES, $channelId);
    }

    public function isEnabledAlternateImages($channelId = null): bool
    {
        return $this->getBool(self::ENABLE_ALTERNATE_IMAGES, $channelId);
    }

    public function isEnabledInventoryLevels($channelId = null): bool
    {
        return $this->getBool(self::ENABLE_INVENTORY_LEVELS, $channelId);
    }

    public function isEnabledSendCustomerDataToNosto($channelId = null): bool
    {
        return $this->getBool(self::ENABLE_SEND_CUSTOMER_DATA_TO_NOSTO, $channelId);
    }

    public function isEnabledSyncInactiveProducts($channelId = null): bool
    {
        return $this->getBool(self::ENABLE_SYNC_INACTIVE_PRODUCTS, $channelId);
    }

    public function isEnabledProductPublishedDateTagging($channelId = null): bool
    {
        return $this->getBool(self::ENABLE_PRODUCT_PUBLISHED_DATE_TAGGING, $channelId);
    }

    public function isEnabledReloadRecommendationsAfterAdding($channelId = null): bool
    {
        return $this->getBool(self::ENABLE_RELOAD_RECOMMENDATIONS_AFTER_ADDING, $channelId);
    }

    public function getAccountId($channelId = null): string
    {
        return $this->getString(self::ACCOUNT_ID, $channelId);
    }

    public function getProductToken($channelId = null): string
    {
        return $this->getString(self::PRODUCT_TOKEN, $channelId);
    }

    public function getEmailToken($channelId = null): string
    {
        return $this->getString(self::EMAIL_TOKEN, $channelId);
    }

    public function getAppToken($channelId = null): string
    {
        return $this->getString(self::APP_TOKEN, $channelId);
    }

    public function getTagFieldKey(int $tagNumber, $channelId = null): string
    {
        return $this->getString(self::TAG_FIELD_TEMPLATE . $tagNumber, $channelId);
    }
}
