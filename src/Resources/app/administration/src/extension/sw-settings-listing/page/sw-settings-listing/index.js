import template from './sw-settings-listing.html.twig';

Shopware.Component.override('sw-settings-listing', {
    template,

    computed: {
        version() {
            const currentVersion = Shopware.Context.app.config.version;
            const compareVersion = '6.6.9';

            const oldParts = compareVersion.split('.');
            const newParts = currentVersion.split('.');

            for (let i = 0; i < newParts.length; i += 1) {
                const a = parseInt(newParts[i], 10);
                const b = parseInt(oldParts[i], 10);

                if (a > b) {
                    return true;
                }
                if (a < b) {
                    return false;
                }
            }
            return false;
        },
    },
});
