import template from './nosto-integration-settings.html.twig';
import './nosto-integration-settings.scss';

const {Component, Defaults, Mixin} = Shopware;
const {Criteria} = Shopware.Data;

Component.register('nosto-integration-settings', {
    template,

    inject: [
        'repositoryFactory',
        'OdNostoProviderService',
    ],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
            defaultAccountNameFilled: false,
            messageAccountBlankErrorState: null,
            config: null,
            salesChannels: [],
            errorStates: {},
            configurationKeys: {
                accountID: 'overdose_nosto.config.accountID',
                accountName: 'overdose_nosto.config.accountName',
                productToken: 'overdose_nosto.config.productToken',
                emailToken: 'overdose_nosto.config.emailToken',
                appToken: 'overdose_nosto.config.appToken'
            }
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        }
    },

    methods: {

        createdComponent() {
            this.getSalesChannels();
        },

        onChangeLanguage() {
            this.getSalesChannels();
        },

        getSalesChannels() {
            this.isLoading = true;

            const criteria = new Criteria();
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
                this.isLoading = false;
            });
        },

        isActive(channelId) {
            return this.$refs.configComponent.allConfigs.hasOwnProperty(channelId) &&
            this.$refs.configComponent.allConfigs[channelId].hasOwnProperty('overdose_nosto.config.isEnabled') &&
            typeof this.$refs.configComponent.allConfigs[channelId]['overdose_nosto.config.isEnabled'] === 'boolean' ?
                this.$refs.configComponent.allConfigs[channelId]['overdose_nosto.config.isEnabled'] : this.$refs.configComponent.allConfigs[null]['overdose_nosto.config.isEnabled'];
        },

        getInheritedValue(channelId, key) {
            return this.$refs.configComponent.allConfigs.hasOwnProperty(channelId) &&
            this.$refs.configComponent.allConfigs[channelId].hasOwnProperty(key) &&
            this.$refs.configComponent.allConfigs[channelId][key] !== null ?
                this.$refs.configComponent.allConfigs[channelId][key] : this.$refs.configComponent.allConfigs[null][key]
        },

        checkErrorsBeforeSave() {
            const BreakException = {};
            let result = false;
            try {
                Object.keys(this.$refs.configComponent.allConfigs).forEach(item => {
                    if (
                        this.isActive(item) &&
                        (!this.getInheritedValue(item, this.configurationKeys.accountID) ||
                            !this.getInheritedValue(item, this.configurationKeys.accountName) ||
                            !this.getInheritedValue(item, this.configurationKeys.productToken) ||
                            !this.getInheritedValue(item, this.configurationKeys.emailToken) ||
                            !this.getInheritedValue(item, this.configurationKeys.appToken)
                        )
                    ) {
                        result = {
                            'salesChannelName': this.salesChannels.get(item == 'null' ? null : item).translated.name
                        }
                        throw BreakException;
                    }
                })
            } catch (e) {
                if (e !== BreakException) throw e;
            }
            return result
        },

        clearCaches() {
            this.createNotificationInfo({
                message: this.$tc('sw-settings-cache.notifications.clearCache.started'),
            });
            this.OdNostoProviderService.clearCaches().then(() => {
                this.createNotificationSuccess({
                    message: this.$tc('sw-settings-cache.notifications.clearCache.success')
                });
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('sw-settings-cache.notifications.clearCache.error'),
                });
            })
        },

        onSave() {
            this.isLoading = true;
            const checkList = this.checkErrorsBeforeSave();
            if (checkList) {
                this.isLoading = false;
                return this.createNotificationError({
                    title: checkList['salesChannelName'], message: this.$tc('nosto.messages.error-message')
                });
            }
            this.$refs.configComponent.save().then(() => {
                this.isSaveSuccessful = true;
                this.createNotificationSuccess({
                    message: this.$tc('nosto.messages.success-message')
                });
                this.clearCaches();
            }).finally(() => {
                this.isLoading = false;
            });
        }
    }
});
