<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Config;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\Exception\InvalidDomainException;
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

    public const ENABLE_MERCH = 'enableMerch';

    public const ENABLE_NOT_LOGGED_IN_CACHE = 'notLoggedInCache';

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

    public const MAIN_VARIANT = 'mainVariant';

    public const ENABLE_VARIATIONS = 'variations';

    public const ENABLE_PRODUCT_PROPERTIES = 'productProperties';

    public const ENABLE_ALTERNATE_IMAGES = 'alternateImages';

    public const ENABLE_INVENTORY_LEVELS = 'inventory';

    public const ENABLE_CUSTOMER_DATA_TO_NOSTO = 'customerDataToNosto';

    public const ENABLE_SYNC_INACTIVE_PRODUCTS = 'syncInactiveProducts';

    public const ENABLE_PRODUCT_PUBLISHED_DATE_TAGGING = 'productPublishedDateTagging';

    public const ENABLE_RELOAD_RECOMMENDATIONS_AFTER_ADDING = 'reloadRecommendations';

    public const ENABLE_PRODUCT_LABELLING_SYNC = 'enableLabelling';

    public const DAILY_PRODUCT_SYNC_ENABLED = 'dailySynchronization';

    public const DAILY_PRODUCT_SYNC_TIME = 'dailySynchronizationTime';

    private array $configs = [];

    public function __construct(
        private readonly EntityRepository $nostoConfigRepository,
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
     * @throws InvalidDomainException
     * @throws InvalidUuidException
     * @throws InconsistentCriteriaIdsException
     */
    public function getConfig(?string $salesChannelId = null, ?string $languageId = null): array
    {
        $criteria = $this->buildCriteria($salesChannelId, $languageId);

        /** @var NostoConfigCollection $collection */
        $collection = $this->nostoConfigRepository
            ->search($criteria, Context::createDefaultContext())
            ->getEntities();

        return $this->buildConfig($collection);
    }

    public function set(string $key, mixed $value, ?string $salesChannelId = null, ?string $languageId = null): void
    {
        $this->configs = [];
        $key = trim($key);
        $this->validate($key, $salesChannelId, $languageId);

        $id = $this->getId($key, $salesChannelId, $languageId);
        if ($value === null) {
            if ($id) {
                $this->nostoConfigRepository->delete([[
                    'id' => $id,
                ]], Context::createDefaultContext());
            }

            return;
        }

        $this->nostoConfigRepository->upsert(
            [
                [
                    'id' => $id ?? Uuid::randomHex(),
                    'configurationKey' => $key,
                    'configurationValue' => $value,
                    'salesChannelId' => $salesChannelId,
                    'languageId' => $languageId,
                ],
            ],
            Context::createDefaultContext(),
        );
    }

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

    private function getId(string $key, ?string $salesChannelId = null, ?string $languageId = null): ?string
    {
        $criteria = $this->buildCriteria($salesChannelId, $languageId, $key);
        $ids = $this->nostoConfigRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

        return array_shift($ids);
    }

    private function buildCriteria(
        ?string $salesChannelId = null,
        ?string $languageId = null,
        ?string $key = null,
    ): Criteria {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        $criteria->addFilter(new EqualsFilter('languageId', $languageId));

        if ($key) {
            $criteria->addFilter(new EqualsFilter('configurationKey', $key));
        }

        return $criteria;
    }

    private function load(?string $salesChannelId = null, ?string $languageId = null): void
    {
        if (!isset($this->configs[self::PARENT_CONFIG_KEY])) {
            $this->configs[self::PARENT_CONFIG_KEY] = $this->loadConfigsFromDatabase();
        }

        $key = $this->buildConfigKey($salesChannelId, $languageId);
        if (!isset($this->configs[$key])) {
            $this->configs[$key] = $this->loadConfigsFromDatabase($salesChannelId, $languageId);
        }
    }

    private function buildConfigKey(?string $salesChannelId = null, ?string $languageId = null): string
    {
        return $salesChannelId ? sprintf('%s-%s', $salesChannelId, $languageId) : self::PARENT_CONFIG_KEY;
    }

    private function loadConfigsFromDatabase(?string $salesChannelId = null, ?string $languageId = null): array
    {
        $criteria = new Criteria();
        $criteria->setTitle('nosto-integration-config::load');

        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_AND,
                [new EqualsFilter('salesChannelId', $salesChannelId), new EqualsFilter('languageId', $languageId)],
            ),
        );

        $criteria->addSorting(new FieldSorting('id', FieldSorting::ASCENDING));

        $criteria->setLimit(500);

        $configs = $this->nostoConfigRepository->search($criteria, Context::createDefaultContext())->getEntities();

        return $this->parseConfiguration($configs);
    }

    private function buildConfig(NostoConfigCollection $configs): array
    {
        $nostoConfig = [];
        foreach ($configs as $config) {
            $keyExists = array_key_exists($config->getConfigurationKey(), $nostoConfig);
            if (!$keyExists || !$this->isEmpty($config->getConfigurationValue())) {
                $nostoConfig[$config->getConfigurationKey()] = $config->getConfigurationValue();
            }
        }

        return $nostoConfig;
    }

    private function parseConfiguration(NostoConfigCollection $collection): array
    {
        $configValues = [];

        foreach ($collection as $config) {
            $configValues[$config->getConfigurationKey()] = $config->getConfigurationValue();
        }

        return $configValues;
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
