<?php declare(strict_types=1);

namespace Od\NostoIntegration\Model;

use Od\Base\Model\AbstractConfigProvider;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigProvider extends AbstractConfigProvider
{
    private SystemConfigService $systemConfig;
    protected string $pathPrefix = 'OdNostoIntegration.';

    public const ENABLE_VARIATIONS = 'general/enable_variations';
    public const ENABLE_PRODUCT_PROPERTIES = 'general/enable_product_properties';
    public const ENABLE_ALTERNATE_IMAGES = 'general/enable_alternate_images';
    public const ENABLE_INVENTORY_LEVELS = 'general/enable_inventory_levels';
    public const ENABLE_SEND_CUSTOMER_DATA_TO_NOSTO = 'general/enable_send_customer_data_to_nosto';
    public const ENABLE_SYNC_INACTIVE_PRODUCTS = 'general/enable_sync_inactive_products';
    public const ENABLE_PRODUCT_PUBLISHED_DATE_TAGGING = 'general/enable_product_published_date_tagging';
    public const ENABLE_RELOAD_RECOMMENDATIONS_AFTER_ADDING = 'general/enable_reload_recommendations_after_adding';
    public const ACCOUNT_ID_FIELD = 'credentials/account_id_field';
    public const PRODUCT_TOKEN_FIELD = 'credentials/product_token_field';

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

    public function getAccountIdField($channelId = null): string
    {
        return $this->getString(self::ACCOUNT_ID_FIELD, $channelId);
    }

    public function getProductTokenField($channelId = null): string
    {
        return $this->getString(self::PRODUCT_TOKEN_FIELD, $channelId);
    }
}
