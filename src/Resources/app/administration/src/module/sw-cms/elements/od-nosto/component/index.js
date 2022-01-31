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

    methods: {
        createdComponent() {
            this.initElementConfig('od-nosto');
        },
    }
});
