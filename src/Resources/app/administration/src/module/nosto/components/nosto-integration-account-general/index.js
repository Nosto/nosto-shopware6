import template from './nosto-integration-account-general.html.twig';

const {Component, Mixin} = Shopware;

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
                accountID: 'NostoIntegration.settings.accounts.accountID',
                accountName: 'NostoIntegration.settings.accounts.accountName',
                productToken: 'NostoIntegration.settings.accounts.productToken',
                emailToken: 'NostoIntegration.settings.accounts.emailToken',
                appToken: 'NostoIntegration.settings.accounts.appToken'
            }
        }
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
                detail: this.$tc('nosto.messages.blank-field-error')
            })
        },

        checkErrorState() {
            for (const [key, value] of Object.entries(this.configurationKeys)) {
                if (!this.allConfigs['null'][value]) {
                    this.createErrorState(key);
                }
            }
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

        validateApiCredentials() {
            this.apiValidationInProgress = true;
            const accountId = this.actualConfigData[this.configurationKeys.accountID];
            const accountName = this.actualConfigData[this.configurationKeys.accountName];
            const productToken = this.actualConfigData[this.configurationKeys.productToken];
            const emailToken = this.actualConfigData[this.configurationKeys.emailToken];
            const appToken = this.actualConfigData[this.configurationKeys.appToken];

            if (!(this.credentialsEmptyValidation('id', accountId) *
                this.credentialsEmptyValidation('name', accountName) *
                this.credentialsEmptyValidation('productToken', productToken) *
                this.credentialsEmptyValidation('emailToken', emailToken) *
                this.credentialsEmptyValidation('appToken', appToken))) {
                this.apiValidationInProgress = false;
                return;
            }
            this.nostoApiKeyValidatorService.validate({
                'accountId': accountId,
                'name': accountName,
                'productToken': productToken,
                'emailToken': emailToken,
                'appToken': appToken
            }).then((response) => {
                if (response.status !== 200) {
                    this.createNotificationError({
                        message: this.$tc('nosto.configuration.account.apiValidation.generalErrorMessage'),
                    });
                    return;
                }
                const data = response.data;
                for (const prop in data) {
                    if (data[prop].success) {
                        this.createNotificationSuccess({
                            title: this.$tc('nosto.configuration.account.' + prop + 'Title'),
                            message: this.$tc('nosto.configuration.account.apiValidation.correctApiMessage'),
                        });
                    } else {
                        this.createNotificationError({
                            title: this.$tc('nosto.configuration.account.' + prop + 'Title'),
                            message: data[prop].message,
                        });
                    }
                }
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
                    message: this.$tc('nosto.configuration.account.apiValidation.emptyErrorMessage', 0, {entityName: this.$tc('nosto.configuration.account.' + key + 'Title')}),
                });
                return false
            }
            return true;
        }
    },
});
