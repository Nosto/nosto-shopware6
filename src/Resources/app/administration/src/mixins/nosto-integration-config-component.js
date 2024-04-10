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
            this.setConfigValue({
                configKey: this.configKey,
                key,
                value,
            });
        },
    },
});
