import template from './nosto-integration-account-general.html.twig';

const { Component, Mixin } = Shopware;

/** @private */
Component.register('nosto-integration-account-general', {
    template,

    inject: ['nostoApiKeyValidatorService', 'NostoCategoriesProviderService'],
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
        selectedLanguageId: {
            type: String,
            required: false,
            default: null,
        },
        configKey: {
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

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.checkErrorState();
        },

        createErrorState(key) {
            this.$set(this.errorStates, key, {
                code: 1,
                detail: this.$tc('nosto.messages.blank-field-error'),
            });
        },

        checkErrorState() {
            this.configurationKeys.forEach(key => {
                if (!this.allConfigs.null[key]) {
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
            const configurationKey = 'isEnabled';
            const channelConfig = this.allConfigs[this.configKey] || {};

            return typeof channelConfig[configurationKey] === 'boolean'
                ? channelConfig[configurationKey]
                : false;
        },

        removeErrorState(key) {
            return this.$delete(this.errorStates, key);
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
            const accountId = this.actualConfigData.accountID;
            const accountName = this.actualConfigData.accountName;
            const productToken = this.actualConfigData.productToken;
            const emailToken = this.actualConfigData.emailToken;
            const appToken = this.actualConfigData.appToken;
            const searchToken = this.actualConfigData.searchToken;

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
