import template from './sw-cms-el-nosto-integration.html.twig';
import './sw-cms-el-nosto-integration.scss';

const { Component, Mixin } = Shopware;

/** @private */
Component.register('sw-cms-el-nosto-integration', {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        labelPreview() {
            const label = this.element.config?.nostoElementID?.value;

            return label || this.$tc('sw-cms.detail.preview.emptyLabel');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('nosto-integration');
        },
    },
});
