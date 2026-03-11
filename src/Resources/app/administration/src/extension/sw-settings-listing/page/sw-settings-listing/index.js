/**
 * @sw-package discovery
 */

import template from './sw-settings-listing.html.twig';

Shopware.Component.override('sw-settings-listing', {
    template,

    computed: {
        versionHavingSearchDefaultSorting() {
            const currentVersion = Shopware.Context.app.config.version;
            const compareVersion = '6.6.9';

            const oldParts = compareVersion.split('.').map(Number);
            const newParts = currentVersion.split('.').map(Number);

            for (let i = 0; i < newParts.length; i += 1) {
                if (newParts[i] !== oldParts[i]) {
                    return newParts[i] > oldParts[i];
                }
            }
            return false;
        },
    },
});
