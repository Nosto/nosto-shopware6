/* eslint-disable sw-core-rules/require-package-annotation */
const { Mixin } = Shopware;

Mixin.register('nosto-integration-config-component', {
    props: {
        configKey: {
            type: String,
            required: false,
            default: null,
        },
    },

    mixins: [
        Mixin.getByName('notification'),
    ],

    computed: {
        nostoStore() {
            return Shopware.Store.get('nostoIntegrationConfig');
        },
        configs() {
            return this.nostoStore.configs;
        },
        currentConfig() {
            return this.configs[this.configKey] || {};
        },
    },

    methods: {
        setDefaultConfigs(defaultConfig) {
            this.nostoStore.setDefaultConfigs(defaultConfig);
        },
        setConfigValue({ configKey, key, value }) {
            this.nostoStore.setConfigValue({ configKey, key, value });
        },
        onUpdateValue(key, value) {
            if (key === 'productIdentifier') {
                this.createNotificationWarning({
                    message: this.$tc('nosto.configuration.featuresFlags.productIdentifierMerchantInfo'),
                });
            }

            this.setConfigValue({
                configKey: this.configKey,
                key,
                value,
            });
        },
    },
});
