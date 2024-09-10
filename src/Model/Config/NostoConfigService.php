<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Config;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Query\QueryBuilder;
use JsonException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ConfigJsonField;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\Exception\InvalidKeyException;
use Shopware\Core\System\SystemConfig\Exception\InvalidSettingValueException;

class NostoConfigService
{
    private const PARENT_CONFIG_KEY = 'parent';

    public const ACCOUNT_ENABLED = 'isEnabled';

    public const ACCOUNT_ID = 'accountID';

    public const ACCOUNT_NAME = 'accountName';

    public const PRODUCT_TOKEN = 'productToken';

    public const EMAIL_TOKEN = 'emailToken';

    public const GRAPHQL_TOKEN = 'appToken';

    public const SEARCH_TOKEN = 'searchToken';

    public const ENABLE_SEARCH = 'enableSearch';

    public const ENABLE_NAVIGATION = 'enableNavigation';

    public const INITIALIZE_NOSTO_AFTER_INTERACTION = 'isInitializeNostoAfterInteraction';

    public const DOMAIN_ID = 'domain';

    public const SELECTED_CUSTOM_FIELDS = 'selectedCustomFields';

    public const TAG_FIELD_TEMPLATE = 'tag';

    public const GOOGLE_CATEGORY = 'googleCategory';

    public const PRODUCT_IDENTIFIER_FIELD = 'productIdentifier';

    public const RATING_REVIEWS = 'ratingsReviews';

    public const STOCK_FIELD = 'stockField';

    public const CROSS_SELLING_SYNC_FIELD = 'crossSellingSync';

    public const CATEGORY_NAMING_FIELD = 'categoryNaming';

    public const CATEGORY_BLOCKLIST = 'categoryBlocklist';

    public const ENABLE_VARIATIONS = 'variations';

    public const ENABLE_PRODUCT_PROPERTIES = 'productProperties';

    public const ENABLE_ALTERNATE_IMAGES = 'alternateImages';

    public const ENABLE_INVENTORY_LEVELS = 'inventory';

    public const ENABLE_CUSTOMER_DATA_TO_NOSTO = 'customerDataToNosto';

    public const ENABLE_SYNC_INACTIVE_PRODUCTS = 'syncInactiveProducts';

    public const ENABLE_PRODUCT_PUBLISHED_DATE_TAGGING = 'productPublishedDateTagging';

    public const ENABLE_RELOAD_RECOMMENDATIONS_AFTER_ADDING = 'reloadRecommendations';

    public const ENABLE_PRODUCT_LABELLING_SYNC = 'enableLabelling';

    public const ENABLE_STORE_ABANDONED_CART_DATA = 'storeAbandonedCartData';

    public const ENABLE_IGNORE_COOKIE_CONSENT = 'ignoreCookieConsent';

    public const DAILY_PRODUCT_SYNC_ENABLED = 'dailySynchronization';

    public const DAILY_PRODUCT_SYNC_TIME = 'dailySynchronizationTime';

    public const OLD_JOB_CLEANUP_ENABLED = 'oldJobCleanup';

    public const OLD_JOB_CLEANUP_PERIOD = 'oldJobCleanupPeriod';

    public const OLD_NOSTO_DATA_CLEANUP_ENABLED = 'oldNostoDataCleanup';

    public const OLD_NOSTO_DATA_CLEANUP_PERIOD = 'oldNostoDataCleanupPeriod';

    private array $configs = [];

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function get(string $key, ?string $salesChannelId = null, ?string $languageId = null): mixed
    {
        $this->load($salesChannelId, $languageId);
        $configKey = $this->buildConfigKey($salesChannelId, $languageId);

        $parentValue = $this->configs[self::PARENT_CONFIG_KEY][$key] ?? null;
        return $this->configs[$configKey][$key] ?? $parentValue;
    }

    public function getString(string $key, ?string $salesChannelId = null, ?string $languageId = null): string
    {
        $value = $this->get($key, $salesChannelId, $languageId);
        if (!is_array($value)) {
            return (string) $value;
        }

        throw new InvalidSettingValueException($key, 'string', gettype($value));
    }

    public function getInt(string $key, ?string $salesChannelId = null, ?string $languageId = null): int
    {
        $value = $this->get($key, $salesChannelId, $languageId);
        if (!is_array($value)) {
            return (int) $value;
        }

        throw new InvalidSettingValueException($key, 'int', gettype($value));
    }

    public function getFloat(string $key, ?string $salesChannelId = null, ?string $languageId = null): float
    {
        $value = $this->get($key, $salesChannelId, $languageId);
        if (!is_array($value)) {
            return (float) $value;
        }

        throw new InvalidSettingValueException($key, 'float', gettype($value));
    }

    public function getBool(string $key, ?string $salesChannelId = null, ?string $languageId = null): bool
    {
        return (bool) $this->get($key, $salesChannelId, $languageId);
    }

    public function getConfigWithInheritance(string $salesChannelId = null, string $languageId = null): array
    {
        $key = $this->buildConfigKey($salesChannelId, $languageId);
        $this->load($salesChannelId, $languageId);

        return array_merge($this->configs[self::PARENT_CONFIG_KEY], $this->configs[$key]);
    }

    /**
     * @throws JsonException
     * @throws Exception
     */
    public function getConfig(?string $salesChannelId = null, ?string $languageId = null): array
    {
        $queryBuilder = $this->fetchDefaultQueryBuilder($salesChannelId, $languageId);
        $databaseConfigs = $queryBuilder->executeQuery()->fetchAllNumeric();

        if (!count($databaseConfigs)) {
            return [];
        }

        $configs = [];

        foreach ($databaseConfigs as [$key, $value]) {
            if ($value !== null) {
                $value = json_decode((string) $value, true, 512, \JSON_THROW_ON_ERROR);

                if ($value === false || !isset($value[ConfigJsonField::STORAGE_KEY])) {
                    $value = null;
                } else {
                    $value = $value[ConfigJsonField::STORAGE_KEY];
                }
            }

            if ($this->isEmpty($value)) {
                continue;
            }

            $configs[$key] = $value;
        }

        return $configs;
    }

    /**
     * @throws Exception
     */
    public function set(string $key, mixed $value, ?string $salesChannelId = null, ?string $languageId = null): void
    {
        $this->configs = [];
        $key = trim($key);
        $this->validate($key, $salesChannelId, $languageId);

        $id = $this->getId($key, $salesChannelId, $languageId);

        if ($this->isEmpty($value)) {
            if ($id) {
                $this->connection->delete(
                    'nosto_integration_config',
                    ['id' => Uuid::fromHexToBytes($id)],
                );
            }

            return;
        }

        if ($id) {
            $this->connection->update(
                'nosto_integration_config',
                [
                    'configuration_value' => Json::encode(['_value' => $value]),
                    'updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
                [
                    'id' => Uuid::fromHexToBytes($id),
                ]
            );
        } else {
            $this->connection->insert(
                'nosto_integration_config',
                [
                    'id' => Uuid::randomBytes(),
                    'configuration_key' => $key,
                    'configuration_value' => Json::encode(['_value' => $value]),
                    'sales_channel_id' => $salesChannelId ? Uuid::fromHexToBytes($salesChannelId) : null,
                    'language_id' => $languageId ? Uuid::fromHexToBytes($languageId) : null,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }
    }

    /**
     * @throws Exception
     */
    public function delete(string $key, ?string $salesChannelId = null, ?string $languageId = null): void
    {
        $this->set($key, null, $salesChannelId, $languageId);
    }

    private function validate(string $key, ?string $salesChannelId = null, ?string $languageId = null): void
    {
        $key = trim($key);
        if ($key === '') {
            throw new InvalidKeyException('Key cannot be empty');
        }
        if ($salesChannelId && !Uuid::isValid($salesChannelId)) {
            throw new InvalidUuidException($salesChannelId);
        }
        if ($languageId && !Uuid::isValid($languageId)) {
            throw new InvalidUuidException($languageId);
        }
    }

    /**
     * @throws Exception
     */
    private function getId(string $key, ?string $salesChannelId = null, ?string $languageId = null): ?string
    {
        $queryBuilder = $this->fetchDefaultQueryBuilder($salesChannelId, $languageId, $key);
        $queryBuilder->addSelect('id');

        $result = $queryBuilder->executeQuery()->fetchAssociative();

        if (!$result || !array_key_exists('id', $result) || $this->isEmpty($result['id'])) {
            return null;
        }

        return Uuid::fromBytesToHex($result['id']);
    }

    private function fetchDefaultQueryBuilder(
        ?string $salesChannelId = null,
        ?string $languageId = null,
        ?string $key = null,
    ): QueryBuilder {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select(['configuration_key', 'configuration_value'])
            ->from('nosto_integration_config');

        if ($salesChannelId) {
            $queryBuilder->andWhere('sales_channel_id = :salesChannelId');
        } else {
            $queryBuilder->andWhere('sales_channel_id IS NULL');
        }

        if ($languageId) {
            $queryBuilder->andWhere('language_id = :languageId');
        } else {
            $queryBuilder->andWhere('language_id IS NULL');
        }

        if ($key) {
            $queryBuilder->andWhere('configuration_key = :key');
        }

        $queryBuilder->addOrderBy('id', 'ASC');
        $queryBuilder->setParameter('salesChannelId', $salesChannelId ? Uuid::fromHexToBytes($salesChannelId) : null);
        $queryBuilder->setParameter('languageId', $languageId ? Uuid::fromHexToBytes($languageId) : null);
        $queryBuilder->setParameter('key', $key);

        return $queryBuilder;
    }

    private function load(?string $salesChannelId = null, ?string $languageId = null): void
    {
        if (!isset($this->configs[self::PARENT_CONFIG_KEY])) {
            $this->configs[self::PARENT_CONFIG_KEY] = $this->getConfig();
        }

        $key = $this->buildConfigKey($salesChannelId, $languageId);
        if (!isset($this->configs[$key])) {
            $this->configs[$key] = $this->getConfig($salesChannelId, $languageId);
        }
    }

    private function buildConfigKey(?string $salesChannelId = null, ?string $languageId = null): string
    {
        return $salesChannelId ? sprintf('%s-%s', $salesChannelId, $languageId) : self::PARENT_CONFIG_KEY;
    }

    private function isEmpty($value): bool
    {
        if (is_numeric($value) || is_object($value) || is_bool($value)) {
            return false;
        }

        if (is_array($value) && empty(array_filter($value))) {
            return true;
        }

        if (is_string($value) && empty(trim($value))) {
            return true;
        }

        if (empty($value)) {
            return true;
        }

        return false;
    }
}
