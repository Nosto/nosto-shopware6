import template from './nosto-integration-account-general.html.twig';

const { Component, Mixin } = Shopware;

/** @private */
Component.register('nosto-integration-account-general', {
    template,

    inject: ['nostoApiKeyValidatorService', 'NostoCategoriesProviderService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('nosto-integration-config-component'),
    ],

    props: {
        selectedSalesChannelId: {
            type: String,
            required: false,
            default: null,
        },
        selectedLanguageId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            apiValidationInProgress: false,
            configurationKeys: ['accountID', 'accountName', 'productToken', 'emailToken', 'appToken', 'searchToken'],
        };
    },

    methods: {
        hasError(value) {
            return this.isActive() && !value ? {
                code: 1,
                detail: this.$tc('nosto.messages.blank-field-error'),
            } : null;
        },

        isActive() {
            const configurationKey = 'isEnabled';
            const channelConfig = this.allConfigs[this.configKey] || {};

            return typeof channelConfig[configurationKey] === 'boolean'
                ? channelConfig[configurationKey]
                : false;
        },

        validateApiCredentials() {
            this.apiValidationInProgress = true;
            const accountId = this.currentConfig.accountID;
            const accountName = this.currentConfig.accountName;
            const productToken = this.currentConfig.productToken;
            const emailToken = this.currentConfig.emailToken;
            const appToken = this.currentConfig.appToken;
            const searchToken = this.currentConfig.searchToken;

            if (!(this.credentialsEmptyValidation('id', accountId) *
                this.credentialsEmptyValidation('name', accountName) *
                this.credentialsEmptyValidation('productToken', productToken) *
                this.credentialsEmptyValidation('emailToken', emailToken) *
                this.credentialsEmptyValidation('appToken', appToken) *
                this.credentialsEmptyValidation('searchToken', searchToken))) {
                this.apiValidationInProgress = false;
                return;
            }

            this.nostoApiKeyValidatorService.validate({
                accountId: accountId,
                name: accountName,
                productToken: productToken,
                emailToken: emailToken,
                appToken: appToken,
                searchToken: searchToken,
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
                            message: value.message ?? 'Unexpected Error',
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

        onTrackCategories() {
            this.NostoCategoriesProviderService.sendCategories(
                this.selectedSalesChannelId,
                this.selectedLanguageId,
            ).then(() => {
                this.createNotificationSuccess({
                    message: 'Synced!',
                });
            }).catch((exception) => {
                console.error(exception);
                this.createNotificationError({
                    message: 'Something went wrong!',
                });
            }).finally(() => {
                this.isLoading = false;
            });
        },
    },
});
