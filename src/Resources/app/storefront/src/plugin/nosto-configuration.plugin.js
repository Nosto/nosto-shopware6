import Plugin from 'src/plugin-system/plugin.class';
import Storage from 'src/helper/storage/storage.helper';
import DomAccess from 'src/helper/dom-access.helper';

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

        let name = "nostojs";
        window[name] = window[name] || function (cb) {
            (window[name].q = window[name].q || []).push(cb);
        };

        if (this.options.accountID) {
            let script = document.createElement('script');
            script.type = 'text/javascript';
            script.setAttribute('async', true);
            script.src = '//connect.nosto.com/include/' + this.options.accountID;

            document.body.appendChild(script);

            this._registerSubscribers();

        }
    }

    _registerSubscribers() {

        this._cartWidget = PluginManager.getPluginInstanceFromElement(
            DomAccess.querySelector(document, '[data-cart-widget]', false),
            'CartWidget'
        );

        this._offCanvasCart = PluginManager.getPluginInstanceFromElement(
            DomAccess.querySelector(document, '[data-offcanvas-cart]', false),
            'OffCanvasCart'
        );

        this._addToCart = PluginManager.getPlugin('AddToCart');

        this._cartWidgetSubscriber();
        this._offCanvasCartSubscriber();
        this._addToCartSubscriber();

    }

    _cartWidgetSubscriber() {
        this._cartWidget.$emitter.subscribe('fetch', () => {
            nostojs(api => {
                if (this._offCanvasCart.options.loadRecommendations) {
                    api.loadRecommendations();
                    console.log('loadRecommendations');
                    this._offCanvasCart.options.loadRecommendations = false;
                }
                api.resendCartTagging();
                console.log('resendCartTagging');
            })
        });
    }

    _offCanvasCartSubscriber() {
        this._offCanvasCart.$emitter.subscribe('offCanvasOpened', (response) => {

            const offCanvasCartElement = DomAccess.querySelector(document, '.' + this._offCanvasCart.options.additionalOffcanvasClass, false);

            if (offCanvasCartElement) {
                console.log(this._offCanvasCart);
                console.log('offCanvasCartElement.innerHTML', offCanvasCartElement.innerHTML)
                console.log('response.detail', response.detail.response)

                if (offCanvasCartElement.innerHTML !== response.detail.response) {
                    console.log('sssss')
                }

            }

            // console.log(response)

            // console.log('offCanvasOpened')
            // this._offCanvasCart.options.loadRecommendations = true;
        });
    }

    _addToCartSubscriber() {

        // const addToCartPluginInstances = PluginManager.getPluginInstances('AddToCart');
        // for (let addToCartPluginInstance of addToCartPluginInstances) {
        //     addToCartPluginInstance.$emitter.subscribe('beforeFormSubmit', () => {
        //         console.log('beforeFormSubmit');
        //     });
        // }

        // console.log('getPluginInstances')

    }

}