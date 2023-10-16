import template from './nosto-integration-account-general.html.twig';

const { Component, Mixin } = Shopware;

/** @private */
Component.register('nosto-integration-account-general', {
    template,

    inject: ['nostoApiKeyValidatorService'],
    mixins: [Mixin.getByName('notification')],

    props: {
        actualConfigData: {
            type: Object,
            required: true,
        },
        allConfigs: {
            type: Object,
            required: true,
        },
        errorStates: {
            type: Object,
            required: true,
        },
        selectedSalesChannelId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            apiValidationInProgress: false,
            configurationKeys: {
                accountID: 'NostoIntegration.config.accountID',
                accountName: 'NostoIntegration.config.accountName',
                productToken: 'NostoIntegration.config.productToken',
                emailToken: 'NostoIntegration.config.emailToken',
                appToken: 'NostoIntegration.config.appToken',
            },
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.checkErrorState();
        },

        getConfig(salesChannelId) {
            const values = this.systemConfigApiService
                .getValues('NostoIntegration.config', salesChannelId);

            return values.myKey;
        },

        createErrorState(key) {
            this.$set(this.errorStates, key, {
                code: 1,
                detail: this.$tc('nosto.messages.blank-field-error'),
            });
        },

        checkErrorState() {
            Object.entries(this.configurationKeys).forEach(([key, value]) => {
                if (!this.allConfigs.null[value]) {
                    this.createErrorState(key);
                }
            });
        },

        hasError(value) {
            return this.isActive() && !value ? {
                code: 1,
                detail: this.$tc('nosto.messages.blank-field-error'),
            } : null;
        },

        isActive() {
            const configKey = 'NostoIntegration.config.isEnabled';
            const channelConfig = this.allConfigs[this.selectedSalesChannelId] || null;

            return channelConfig?.hasOwnProperty(configKey) && typeof channelConfig[configKey] === 'boolean'
                ? channelConfig[configKey]
                : this.allConfigs.null[configKey];
        },

        removeErrorState(key) {
            return this.$delete(this.errorStates, key);
        },

        validateRequiredField(key, props) {
            if (props.currentValue.length === 0) {
                return this.createErrorState(key);
            }

            return this.removeErrorState(key);
        },

        checkTextFieldInheritance(value) {
            if (typeof value !== 'string') {
                return true;
            }

            return value.length <= 0;
        },

        updateCurrentValue(value, props) {
            props.updateCurrentValue(value === undefined || value.trim() === '' ? null : value);
        },

        validateApiCredentials() {
            this.apiValidationInProgress = true;
            const accountId = this.getInheritedConfig(this.configurationKeys.accountID);
            const accountName = this.getInheritedConfig(this.configurationKeys.accountName);
            const productToken = this.getInheritedConfig(this.configurationKeys.productToken);
            const emailToken = this.getInheritedConfig(this.configurationKeys.emailToken);
            const appToken = this.getInheritedConfig(this.configurationKeys.appToken);

            if (!(this.credentialsEmptyValidation('id', accountId) *
                this.credentialsEmptyValidation('name', accountName) *
                this.credentialsEmptyValidation('productToken', productToken) *
                this.credentialsEmptyValidation('emailToken', emailToken) *
                this.credentialsEmptyValidation('appToken', appToken))) {
                this.apiValidationInProgress = false;
                return;
            }
            this.nostoApiKeyValidatorService.validate({
                accountId: accountId,
                name: accountName,
                productToken: productToken,
                emailToken: emailToken,
                appToken: appToken,
            }).then((response) => {
                if (response.status !== 200) {
                    this.createNotificationError({
                        message: this.$tc('nosto.configuration.account.apiValidation.generalErrorMessage'),
                    });
                    return;
                }

                Object.entries(response.data).forEach(([prop, value]) => {
                    if (value.success) {
                        this.createNotificationSuccess({
                            title: this.$tc(`nosto.configuration.account.${prop}Title`),
                            message: this.$tc('nosto.configuration.account.apiValidation.correctApiMessage'),
                        });
                    } else {
                        this.createNotificationError({
                            title: this.$tc(`nosto.configuration.account.${prop}Title`),
                            message: value.message,
                        });
                    }
                });
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('nosto.configuration.account.apiValidation.generalErrorMessage'),
                });
            }).finally(() => {
                this.apiValidationInProgress = false;
            });
        },

        getInheritedConfig(key) {
            return this.actualConfigData.hasOwnProperty(key) && this.actualConfigData[key]
                ? this.actualConfigData[key]
                : this.allConfigs.null[key];
        },

        credentialsEmptyValidation(key, value) {
            if (value === undefined || value === '' || value === null) {
                this.createNotificationError({
                    message: this.$tc(
                        'nosto.configuration.account.apiValidation.emptyErrorMessage',
                        0,
                        { entityName: this.$tc(`nosto.configuration.account.${key}Title`) },
                    ),
                });
                return false;
            }
            return true;
        },
    },
});
