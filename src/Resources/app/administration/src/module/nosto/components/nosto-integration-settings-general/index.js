import template from './nosto-integration-settings-general.html.twig';

const { Component } = Shopware;

Component.register('nosto-integration-settings-general', {
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
        selectedSalesChannelId: {
            type: String,
            required: false,
            default: null,
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const configPrefix = 'NostoIntegration.settings.',
                defaultConfigs = {
                    pagesListSynchronizationInterval: 10,
                    pageCacheDuration: 3600,
                };

            /**
             * Initialize config data with default values.
             */
            for (const [key, defaultValue] of Object.entries(defaultConfigs)) {
                if (this.allConfigs['null'][configPrefix + key] === undefined) {
                    this.$set(this.allConfigs['null'], configPrefix + key, defaultValue);
                }
            }
        }
    },
});
