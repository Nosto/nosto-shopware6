import Plugin from 'src/plugin-system/plugin.class';
import Iterator from 'src/helper/iterator.helper';
import CookieStorage from 'src/helper/storage/cookie-storage.helper';
import { NOSTO_COOKIE_KEY } from './nosto-configuration.plugin';

export default class NostoSearchSessionParams extends Plugin {
    init() {
        this.nostoSubscriber();
    }

    nostoSubscriber() {
        const instances = window.PluginManager.getPluginInstances('NostoConfiguration');

        Iterator.iterate(instances, instance => {
            instance.$emitter.subscribe('scriptLoaded', () => {
                if (CookieStorage.getItem(NOSTO_COOKIE_KEY)) {
                    window.nostojs(api => {
                        api.getSearchSessionParams().then(function(response) {
                            CookieStorage.setItem(
                                'nosto-search-session-params',
                                encodeURIComponent(JSON.stringify(response)),
                                30
                            )
                        });
                    });
                }
            });
        });
    }
}
