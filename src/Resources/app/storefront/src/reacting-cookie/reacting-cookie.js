import {COOKIE_CONFIGURATION_UPDATE} from 'src/plugin/cookie/cookie-configuration.plugin';
import Iterator from 'src/helper/iterator.helper';

document.$emitter.subscribe(COOKIE_CONFIGURATION_UPDATE, eventCallback);

function eventCallback(updatedCookies) {
    if (updatedCookies.detail['nosto-integration-track-allow']) {
        Iterator.iterate(window.PluginManager.getPluginInstances('NostoConfiguration'), (plugin) => {
            plugin.onNostoCookieConsentAllowed();
        })
    }
}
