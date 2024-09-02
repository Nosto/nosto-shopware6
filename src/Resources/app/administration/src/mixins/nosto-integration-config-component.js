const { Mixin } = Shopware;

const {
    mapState,
    mapMutations,
} = Shopware.Component.getComponentHelper();

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
        ...mapState('nostoIntegrationConfig', [
            'configs',
        ]),
        currentConfig() {
            return this.configs[this.configKey] || {};
        },
    },

    methods: {
        ...mapMutations('nostoIntegrationConfig', [
            'setDefaultConfigs',
            'setConfigValue',
        ]),
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
