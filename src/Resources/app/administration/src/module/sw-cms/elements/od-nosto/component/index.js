import template from './sw-cms-el-od-nosto.html.twig';
import './sw-cms-el-od-nosto.scss';

const {Component, Mixin} = Shopware;

Component.register('sw-cms-el-od-nosto', {
    template,

    mixins: [
        Mixin.getByName('cms-element')
    ],

    created() {
        this.createdComponent();
    },

    computed: {
        labelPreview() {
            const label = this.element.config?.nostoElementID?.value;

            return label ? label : this.$tc('sw-cms.detail.preview.emptyLabel');
        }
    },

    methods: {
        createdComponent() {
            this.initElementConfig('od-nosto');
        },
    }
});
