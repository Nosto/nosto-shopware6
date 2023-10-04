import template from './sw-cms-el-config-nosto-integration.html.twig';

const {Component, Mixin} = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-cms-el-config-nosto-integration', {
    template,

    mixins: [
        Mixin.getByName('cms-element')
    ]
})

