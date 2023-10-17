import Plugin from 'src/plugin-system/plugin.class';
import Iterator from 'src/helper/iterator.helper';
import NostoCookie from '../util/cookie';

export default class NostoSearchSessionParams extends Plugin {
    init() {
        this.nostoSubscriber();
    }

    nostoSubscriber() {
        const instances = window.PluginManager.getPluginInstances('NostoConfiguration');

        Iterator.iterate(instances, instance => {
            instance.$emitter.subscribe('scriptLoaded', () => {
                if (NostoCookie.getCookie('nosto-integration-track-allow')) {
                    window.nostojs(api => {
                        api.getSearchSessionParams().then(function(response) {
                            NostoCookie.setCookie(
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
