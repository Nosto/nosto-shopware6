import template from './nosto-integration-account-general.html.twig';

const { Component } = Shopware;

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
            if (!this.allConfigs['null']['NostoIntegration.settings.accounts.accountID']) {
                this.createErrorState('accountID');
            }

            if (!this.allConfigs['null']['NostoIntegration.settings.accounts.productToken']) {
                this.createErrorState('productToken');
            }

            if (!this.allConfigs['null']['NostoIntegration.settings.accounts.emailToken']) {
                this.createErrorState('emailToken');
            }

            if (!this.allConfigs['null']['NostoIntegration.settings.accounts.appToken']) {
                this.createErrorState('appToken');
            }
        },

        removeErrorState(key) {
            return this.$delete(this.errorStates, key);
        },

        checkValue(key, props) {
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
