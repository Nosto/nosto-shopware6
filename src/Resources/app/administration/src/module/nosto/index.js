import './pages/nosto-integration-settings';
import './components/nosto-integration-settings-general';
import './components/nosto-integration-account-general';
import './components/nosto-plugin-settings-icon';

import enGB from './snippet/en-GB.json';
import deDE from './snippet/de-DE.json';

const { Module } = Shopware;

Module.register('nosto-integration', {
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
        'settings': {
            component: 'nosto-integration-settings',
            path: 'settings',
            meta: {
                parentPath: 'sw.settings.index.plugins'
            }
        }
    },

    settingsItem: {
        group: 'plugins',
        to: 'nosto.integration.settings',
        iconComponent: 'nosto-plugin-settings-icon',
        backgroundEnabled: true
    },
});
