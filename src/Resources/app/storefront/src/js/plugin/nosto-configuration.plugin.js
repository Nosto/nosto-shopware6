import Storage from 'src/helper/storage/storage.helper';
import DomAccess from 'src/helper/dom-access.helper';
import Iterator from 'src/helper/iterator.helper';
import CookieStorage from 'src/helper/storage/cookie-storage.helper';
import { COOKIE_CONFIGURATION_UPDATE } from 'src/plugin/cookie/cookie-configuration.plugin';

export const NOSTO_COOKIE_KEY = 'nosto-integration-allowed';

// Former cookie name; still honored so already-consented shoppers keep working.
export const LEGACY_NOSTO_COOKIE_KEY = 'nosto-integration-track-allow';

export default class NostoConfiguration extends window.PluginBaseClass {
    static options = {
        nostoInitializedStorageKey: 'nostoInitializedStorageKey',
        cookieWatchInterval: 1000,
        doNotTrack: false,
    };

    init() {
        this._cookieWatcher = null;
        this._scriptInjected = false;
        this._initNosto();
        this.cookieSubscriber();
        this.watchCookieConsent();
    }

    _hasConsentCookie() {
        return CookieStorage.getItem(NOSTO_COOKIE_KEY) || CookieStorage.getItem(LEGACY_NOSTO_COOKIE_KEY);
    }

    _registerInitializationEvents() {
        window.addEventListener('scroll', this._prepareForInitialization.bind(this), {once: true});
    }

    _prepareForInitialization() {
        this.storage.setItem(this.options.nostoInitializedStorageKey, '');
        this._placeClientScript();
    }

    _initNosto() {
        if (this._hasConsentCookie()) {
            this.storage = Storage;

            if (this.options.initializeAfter) {
                if (this.storage.getItem(this.options.nostoInitializedStorageKey) !== null) {
                    return this._placeClientScript();
                } else {
                    return this._registerInitializationEvents();
                }
            }
            this._placeClientScript();
        }
    }

    _placeClientScript() {
        if (this._scriptInjected) {
            return;
        }

        const name = 'nostojs';
        window[name] = window[name] || function (cb) {
            (window[name].q = window[name].q || []).push(cb);
        };

        // Opt out of session tracking before any request is made to Nosto.
        // Queued first so it runs before the client script issues requests.
        if (this.options.doNotTrack) {
            window[name](api => api.visit.setDoNotTrack(true));
        }

        if (this.options.accountID) {
            const script = document.createElement('script');
            script.type = 'text/javascript';
            script.setAttribute('async', true);
            script.src = '//connect.nosto.com/include/' + this.options.accountID;
            script.onload = () => {
                this.$emitter.publish('scriptLoaded');
            };

            document.body.appendChild(script);
            this._scriptInjected = true;
            this.clearCookieWatcher();

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
        if (this._cartWidget !== false) {
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
                    api.reportAddToCart(event.detail, event.detail.elementId);

                    if (this.options.reloadRecommendations) {
                        api.loadRecommendations();
                    }
                });
            });
        });
    }

    cookieSubscriber() {
        // Nosto loads only once the shopper has actively consented via the cookie banner.
        // We intentionally do NOT set the consent cookie on "Deny" — declining must mean
        // Nosto is never initialized (no ev1 requests). Accepting fires this update event
        // (and watchCookieConsent polls as a fallback), which then boots Nosto.
        document.$emitter.subscribe(COOKIE_CONFIGURATION_UPDATE, () => {
            this._initNosto();
        });
    }

    watchCookieConsent() {
        if (this._hasConsentCookie()) {
            return;
        }

        if (this._cookieWatcher) {
            return;
        }

        this._cookieWatcher = window.setInterval(() => {
            if (!this._hasConsentCookie()) {
                return;
            }

            this.clearCookieWatcher();
            this._initNosto();
        }, this.options.cookieWatchInterval);
    }

    clearCookieWatcher() {
        if (!this._cookieWatcher) {
            return;
        }

        window.clearInterval(this._cookieWatcher);
        this._cookieWatcher = null;
    }

    destroy() {
        this.clearCookieWatcher();

        if (super.destroy) {
            super.destroy();
        }
    }
}
