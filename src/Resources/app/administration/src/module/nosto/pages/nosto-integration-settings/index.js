import template from './nosto-integration-settings.html.twig';
import './nosto-integration-settings.scss';

const { Component, Defaults, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/** @private */
Component.register('nosto-integration-settings', {
    template,

    inject: [
        'repositoryFactory',
        'NostoIntegrationProviderService',
        'NostoConfigApiService',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            salesChannelsLoading: false,
            configsLoading: false,
            saving: false,
            isSaveSuccessful: false,
            allConfigs: {},
            salesChannels: [],
            selectedSalesChannelId: null,
            selectedLanguageId: null,
            errorStates: {},
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },
        nostoConfigRepository() {
            return this.repositoryFactory.create('nosto_integration_config');
        },
        configKey() {
            if (!this.selectedSalesChannelId || !this.selectedLanguageId) {
                return null;
            }

            return `${this.selectedSalesChannelId}-${this.selectedLanguageId}`;
        },
        languages() {
            if (!this.selectedSalesChannelId) {
                return [];
            }

            return this.salesChannels.find(
                salesChannel => salesChannel.id === this.selectedSalesChannelId,
            )?.languages || [];
        },
        actualConfigData() {
            return this.allConfigs[this.configKey] || {};
        },
    },

    watch: {
        configKey: {
            handler(newKey) {
                if (!this.allConfigs[newKey]) {
                    this.$set(this.allConfigs, newKey, {});
                }
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getAllConfigs();
            this.getSalesChannels();
        },

        getSalesChannels() {
            this.salesChannelsLoading = true;

            const criteria = new Criteria();
            criteria.addAssociation('languages');
            criteria.addAssociation('domains');
            criteria.addFilter(Criteria.equals('active', true));
            criteria.addFilter(Criteria.equalsAny('typeId', [
                Defaults.storefrontSalesChannelTypeId,
                Defaults.apiSalesChannelTypeId,
            ]));

            this.salesChannelRepository.search(criteria, Shopware.Context.api).then(res => {
                res.add({
                    id: null,
                    translated: {
                        name: this.$tc('sw-sales-channel-switch.labelDefaultOption'),
                    },
                });

                this.salesChannels = res;
            }).finally(() => {
                this.salesChannelsLoading = false;
            });
        },

        getAllConfigs() {
            const criteria = new Criteria();

            this.nostoConfigRepository.search(criteria, Shopware.Context.api).then(res => {
                const configs = {
                    null: {}
                };

                res.forEach(item => {
                    const { salesChannelId, languageId, configurationKey, configurationValue } = item;
                    const key = salesChannelId ? `${salesChannelId}-${languageId}` : null;

                    if (!configs[key]) {
                        configs[key] = {};
                    }

                    configs[key][configurationKey] = configurationValue;
                });

                this.allConfigs = configs;
            });
        },

        isActive(configKey) {
            const key = 'isEnabled';
            const channelConfig = this.allConfigs[configKey] || {};

            return typeof channelConfig[key] === 'boolean' ? channelConfig[key] : this.allConfigs.null[key];
        },

        getInheritedValue(configKey, key) {
            return this.allConfigs[configKey]?.[key] ?? this.allConfigs.null[key];
        },

        checkErrorsBeforeSave() {
            const result = [];

            Object.keys(this.allConfigs).forEach(configKey => {
                if (
                    this.isActive(configKey) &&
                    (!this.getInheritedValue(configKey, 'accountID') ||
                        !this.getInheritedValue(configKey, 'accountName') ||
                        !this.getInheritedValue(configKey, 'productToken') ||
                        !this.getInheritedValue(configKey, 'emailToken') ||
                        !this.getInheritedValue(configKey, 'appToken') ||
                        !this.getInheritedValue(configKey, 'searchToken')
                    )
                ) {
                    const [salesChannelId, languageId] = configKey === 'null'
                        ? [null, null]
                        : configKey.split('-');

                    result.push({
                        salesChannelName: this.salesChannels.get(salesChannelId).translated.name,
                        languageName: this.languages?.find(
                            language => language.id === languageId,
                        )?.name || 'No language selected',
                    });
                }
            });

            return result;
        },

        clearCaches() {
            this.createNotificationInfo({
                message: this.$tc('sw-settings-cache.notifications.clearCache.started'),
            });
            this.NostoIntegrationProviderService.clearCaches().then(() => {
                this.createNotificationSuccess({
                    message: this.$tc('sw-settings-cache.notifications.clearCache.success'),
                });
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('sw-settings-cache.notifications.clearCache.error'),
                });
            });
        },

        onSave() {
            this.saving = true;

            const errors = this.checkErrorsBeforeSave();
            if (errors.length) {
                this.saving = false;
                errors.forEach(error => {
                    this.createNotificationError({
                        title: `${error.salesChannelName} - ${error.languageName}`,
                        message: this.$tc('nosto.messages.error-message'),
                    });
                });
                return;
            }

            this.NostoConfigApiService.batchSave(this.allConfigs)
                .then(() => {
                    this.isSaveSuccessful = true;
                    this.createNotificationSuccess({
                        message: this.$tc('nosto.messages.success-message'),
                    });
                    this.clearCaches();
                })
                .finally(() => {
                    this.saving = false;
                });
        },
    },
});
