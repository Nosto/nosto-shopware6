import template from './nosto-plugin-settings-icon.html.twig';

const { Component } = Shopware;

/** @private */
Component.register('nosto-plugin-settings-icon', {
    template,

    props: {
        size: {
            type: String,
            required: false,
            default: '30',
        },
    },
});
