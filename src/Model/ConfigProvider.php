<?php declare(strict_types=1);

namespace Od\NostoIntegration\Model;

use Od\Base\Model\AbstractConfigProvider;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigProvider extends AbstractConfigProvider
{
    private SystemConfigService $systemConfig;
    protected string $pathPrefix = 'OdNostoIntegration.';

    public const LOG_ENABLE_VARIATIONS = 'general/log_enable_variations';
    public const LOG_ENABLE_PRODUCT_PROPERTIES = 'general/log_enable_product_properties';
    public const LOG_ENABLE_ALTERNATE_IMAGES = 'general/log_enable_alternate_images';
    public const LOG_ENABLE_INVENTORY_LEVELS = 'general/log_enable_inventory_levels';
    public const LOG_ENABLE_SEND_CUSTOMER_DATA_TO_NOSTO = 'general/log_enable_send_customer_data_to_nosto';
    public const LOG_ENABLE_SYNC_INACTIVE_PRODUCTS = 'general/log_enable_sync_inactive_products';
    public const LOG_ENABLE_PRODUCT_PUBLISHED_DATE_TAGGING = 'general/log_enable_product_published_date_tagging';
    public const LOG_ENABLE_RELOAD_RECOMMENDATIONS_AFTER_ADDING = 'general/log_enable_reload_recommendations_after_adding';
    public const ACCOUNT_ID_FIELD = 'credentials/account_id_field';
    public const PRODUCT_TOKEN_FIELD = 'credentials/product_token_field';

    public function __construct(SystemConfigService $systemConfig)
    {
        parent::__construct($systemConfig);
        $this->systemConfig = $systemConfig;
    }

    public function isEnabledLogVariations($channelId = null): bool
    {
        return $this->getBool(self::LOG_ENABLE_VARIATIONS, $channelId);
    }

    public function isEnabledLogProductProperties($channelId = null): bool
    {
        return $this->getBool(self::LOG_ENABLE_PRODUCT_PROPERTIES, $channelId);
    }

    public function isEnabledLogAlternateImages($channelId = null): bool
    {
        return $this->getBool(self::LOG_ENABLE_ALTERNATE_IMAGES, $channelId);
    }

    public function isEnabledLogInventoryLevels($channelId = null): bool
    {
        return $this->getBool(self::LOG_ENABLE_INVENTORY_LEVELS, $channelId);
    }

    public function isEnabledLogSendCustomerDataToNosto($channelId = null): bool
    {
        return $this->getBool(self::LOG_ENABLE_SEND_CUSTOMER_DATA_TO_NOSTO, $channelId);
    }

    public function isEnabledLogSyncInactiveProducts($channelId = null): bool
    {
        return $this->getBool(self::LOG_ENABLE_SYNC_INACTIVE_PRODUCTS, $channelId);
    }

    public function isEnabledLogProductPublishedDateTagging($channelId = null): bool
    {
        return $this->getBool(self::LOG_ENABLE_PRODUCT_PUBLISHED_DATE_TAGGING, $channelId);
    }

    public function isEnabledLogReloadRecommendationsAfterAdding($channelId = null): bool
    {
        return $this->getBool(self::LOG_ENABLE_RELOAD_RECOMMENDATIONS_AFTER_ADDING, $channelId);
    }

    public function getAccountIdField($channelId = null): string
    {
        return $this->getString(self::ACCOUNT_ID_FIELD, $channelId);
    }

    public function getProductTokenField($channelId = null): string
    {
        return $this->getString(self::PRODUCT_TOKEN_FIELD, $channelId);
    }
}
