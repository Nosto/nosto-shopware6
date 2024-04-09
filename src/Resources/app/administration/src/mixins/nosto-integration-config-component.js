const { Mixin } = Shopware;

Mixin.register('nosto-integration-config-component', {
    emits: ['update:allConfigs'],

    props: {
        allConfigs: {
            type: Object,
            required: true,
        },
        configKey: {
            type: String,
            required: false,
            default: null,
        },
    },

    computed: {
        currentConfig: {
            get() {
                return this.allConfigs[this.configKey] || {};
            },
            set(newValue) {
                this.$emit('update:allConfigs', {
                    ...this.allConfigs,
                    [this.configKey]: newValue,
                });
            },
        },
    },

    methods: {
        onUpdateValue(key, value) {
            this.currentConfig = {
                ...this.currentConfig,
                [key]: value,
            };
        },
    },
});
