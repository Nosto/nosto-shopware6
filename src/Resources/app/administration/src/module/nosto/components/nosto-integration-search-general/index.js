import template from './nosto-integration-search-general.html.twig';

const { Component, Mixin } = Shopware;

/** @private */
Component.register('nosto-integration-search-general', {
    template,

    mixins: [
        Mixin.getByName('nosto-integration-config-component'),
    ],

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const defaultConfigs = {
                enableSearch: false,
                enableNavigation: false,
            };

            this.$emit('update:allConfigs', {
                ...this.allConfigs,
                null: {
                    ...defaultConfigs,
                    ...this.allConfigs.null,
                },
            });
        },
    },
});
