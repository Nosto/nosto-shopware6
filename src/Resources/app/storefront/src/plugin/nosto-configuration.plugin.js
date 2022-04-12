import Plugin from 'src/plugin-system/plugin.class';
import Storage from 'src/helper/storage/storage.helper';
import DomAccess from 'src/helper/dom-access.helper';
import Iterator from 'src/helper/iterator.helper';

export default class NostoConfiguration extends Plugin {
    static options = {
        nostoInitializedStorageKey: 'nostoInitializedStorageKey'
    };

    init() {
        this.storage = Storage;

        if (this.options.initializeAfter) {
            if (this.storage.getItem(this.options.nostoInitializedStorageKey) !== null) {
                return this._initNosto();
            } else {
                return this.registerEvents();
            }
        }

        this._initNosto()
    }

    registerEvents() {
        window.addEventListener('scroll', this._prepareForInitialization.bind(this), {once: true});
    }

    _prepareForInitialization() {
        this.storage.setItem(this.options.nostoInitializedStorageKey, '')
        this._initNosto();
    }

    _initNosto() {
        const name = "nostojs";
        window[name] = window[name] || function (cb) {
            (window[name].q = window[name].q || []).push(cb);
        };

        if (this.options.accountID) {
            const script = document.createElement('script');
            script.type = 'text/javascript';
            script.setAttribute('async', true);
            script.src = '//connect.nosto.com/include/' + this.options.accountID;

            document.body.appendChild(script);

            this.registerSubscribers();

        }
    }

    registerSubscribers() {
        this._cartWidget = PluginManager.getPluginInstanceFromElement(
            DomAccess.querySelector(document, '[data-cart-widget]', false),
            'CartWidget'
        );

        this.cartWidgetSubscriber();
        this.nostoSubscriber();

    }

    cartWidgetSubscriber() {
        this._cartWidget.$emitter.subscribe('fetch', () => {
            nostojs(api => {
                api.resendCartTagging();
            });
        });
    }

    nostoSubscriber() {
        const instances = PluginManager.getPluginInstances('NostoPlugin');
        Iterator.iterate(instances, instance => {
            instance.$emitter.subscribe('addRecommendationToCart', (event) => {
                nostojs(api => {
                    api.recommendedProductAddedToCart(event.detail.productId, event.detail.elementId);
                    api.loadRecommendations();
                });
            });
        });
    }

}