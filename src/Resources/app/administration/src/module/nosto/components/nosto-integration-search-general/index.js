import template from './nosto-integration-search-general.html.twig';

const { Component } = Shopware;

/** @private */
Component.register('nosto-integration-search-general', {
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
            const configPrefix = 'NostoIntegration.config.';
            const defaultConfigs = {
                enableSearch: false,
                enableNavigation: false,
            };

            /**
             * Initialize config data with default values.
             */
            Object.entries(defaultConfigs).forEach(([key, defaultValue]) => {
                if (this.allConfigs.null[configPrefix + key] === undefined) {
                    this.$set(this.allConfigs.null, configPrefix + key, defaultValue);
                }
            });
        },
    },
});
