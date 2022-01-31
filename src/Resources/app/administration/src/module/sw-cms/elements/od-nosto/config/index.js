import template from './sw-cms-el-config-od-nosto.html.twig';

const {Component, Mixin} = Shopware;
const { Criteria } = Shopware.Data;


Component.register('sw-cms-el-config-od-nosto', {
    template,

    mixins: [
        Mixin.getByName('cms-element')
    ]
})

