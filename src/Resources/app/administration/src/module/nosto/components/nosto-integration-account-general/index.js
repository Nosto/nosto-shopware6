import template from './nosto-integration-account-general.html.twig';

const {Component} = Shopware;

Component.register('nosto-integration-account-general', {
    template,

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
    },
});
