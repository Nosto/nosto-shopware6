import './pages/nosto-integration-settings';
import './components/nosto-integration-settings-general';
import './components/nosto-integration-account-general';
import './components/nosto-plugin-settings-icon';
import './pages/nosto-job-listing';
import './components/nosto-integration-features-flags'

import enGB from './snippet/en-GB.json';
import deDE from './snippet/de-DE.json';

const { Module } = Shopware;

Module.register('nosto-integration-module', {
    type: 'plugin',
    name: 'nosto-integration',
    title: 'nosto.title',
    description: 'nosto.description',
    color: '#ffd53d',
    icon: 'small-default-stack-line2',

    snippets: {
        'en-GB': enGB,
        'de-DE': deDE,
    },

    routes: {
        list: {
            component: 'nosto-job-listing',
            path: 'list'
        },
        settings: {
            component: 'nosto-integration-settings',
            path: 'settings',
            meta: {
                parentPath: 'sw.settings.index.plugins'
            }
        },
        index: {
            component: 'nosto-integration-settings',
            path: 'index',
            meta: {
                parentPath: 'sw.extension.my-extensions'
            }
        }
    },

    extensionEntryRoute: {
        extensionName: 'overdose_nosto',
        route: 'nosto.integration.module.index'
    },

    settingsItem: {
        group: 'plugins',
        to: 'nosto.integration.module.settings',
        iconComponent: 'nosto-plugin-settings-icon',
        backgroundEnabled: true
    },

    navigation: [{
        label: 'nosto.job.navigation.label',
        color: '#ff3d58',
        path: 'nosto.integration.module.list',
        icon: 'default-object-marketing',
        parent: 'sw-marketing',
        position: 100
    }],
});
