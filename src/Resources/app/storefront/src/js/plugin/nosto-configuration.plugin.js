import Plugin from 'src/plugin-system/plugin.class';
import Storage from 'src/helper/storage/storage.helper';
import DomAccess from 'src/helper/dom-access.helper';
import Iterator from 'src/helper/iterator.helper';
import CookieStorage from 'src/helper/storage/cookie-storage.helper';
import { COOKIE_CONFIGURATION_UPDATE } from 'src/plugin/cookie/cookie-configuration.plugin';

export const NOSTO_COOKIE_KEY = 'nosto-integration-track-allow'

export default class NostoConfiguration extends Plugin {
    static options = {
        nostoInitializedStorageKey: 'nostoInitializedStorageKey',
    };

    init() {
        this._initNosto();
        this.cookieSubscriber();
    }

    _registerInitializationEvents() {
        window.addEventListener('scroll', this._prepareForInitialization.bind(this), {once: true});
    }

    _prepareForInitialization() {
        this.storage.setItem(this.options.nostoInitializedStorageKey, '')
        this._placeClientScript();
    }

    _initNosto() {
        if (CookieStorage.getItem(NOSTO_COOKIE_KEY)) {
            this.storage = Storage;

            if (this.options.initializeAfter) {
                if (this.storage.getItem(this.options.nostoInitializedStorageKey) !== null) {
                    return this._placeClientScript();
                } else {
                    return this._registerInitializationEvents();
                }
            }
            this._placeClientScript()
        }
    }

    _placeClientScript() {
        const name = 'nostojs';
        window[name] = window[name] || function (cb) {
            (window[name].q = window[name].q || []).push(cb);
        };

        if (this.options.accountID) {
            const script = document.createElement('script');
            script.type = 'text/javascript';
            script.setAttribute('async', true);
            script.src = '//connect.nosto.com/include/' + this.options.accountID;
            script.onload = () => {
                this.$emitter.publish('scriptLoaded');
            }

            document.body.appendChild(script);

            this.registerSubscribers();
        }
    }

    registerSubscribers() {
        this._cartWidgetElement = DomAccess.querySelector(document, '[data-cart-widget]', false);
        this._cartWidget = this._cartWidgetElement === false ? false : window.PluginManager.getPluginInstanceFromElement(
            this._cartWidgetElement,
            'CartWidget'
        );

        this.cartWidgetSubscriber();
        this.nostoSubscriber();
    }

    cartWidgetSubscriber() {
        if(this._cartWidget !== false) {
            this._cartWidget.$emitter.subscribe('fetch', () => {
                window.nostojs(api => {
                    api.resendCartTagging();
                });
            });
        }
    }

    nostoSubscriber() {
        const instances = window.PluginManager.getPluginInstances('NostoPlugin');
        Iterator.iterate(instances, instance => {
            instance.$emitter.subscribe('addRecommendationToCart', (event) => {
                window.nostojs(api => {
                    api.recommendedProductAddedToCart(event.detail.productId, event.detail.elementId);

                    if (this.options.reloadRecommendations) {
                        api.loadRecommendations();
                    }
                });
            });
        });
    }

    cookieSubscriber() {
        const instances = window.PluginManager.getPluginInstances('CookiePermission');
        Iterator.iterate(instances, instance => {
            instance.$emitter.subscribe('onClickDenyButton', () => {
                // The deny button accepts the technical cookies, so we can set the Nosto cookie as well
                CookieStorage.setItem(NOSTO_COOKIE_KEY, '1', '30');

                this._initNosto();
            });
        });

        document.$emitter.subscribe(COOKIE_CONFIGURATION_UPDATE, () => {
            this._initNosto();
        });
    }
}
