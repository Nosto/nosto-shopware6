import Plugin from 'src/plugin-system/plugin.class';
import PluginManager from 'src/plugin-system/plugin.manager';
import Iterator from 'src/helper/iterator.helper';

export default class NostoPlugin extends Plugin {

    static options = {
        redirectTo: 'frontend.cart.offcanvas',
        action: '/checkout/line-item/add',
        nostoElementSelector: '.nosto_element'
    };

    init() {
        var self = this;
        window.Nosto = {};
        
        Nosto.addProductToCart = function(id, element) {
           self._onAddToCart(id);
        }

        this._nostoElementId = (this.el.nextElementSibling.id ? this.el.nextElementSibling.id : 'empty');

    }

    _onAddToCart(id) {
        this.csrf_token = document.querySelector('.nosto-csrf-token input').value;

        const productId = id;
        const productData = {
            id: productId,
            type: 'product',
            referencedId: productId,
            stackable: 1,
            removable: 1,
        };
        const data = {
            lineItems: {},
            redirectParameters: {
                productId: productId
            },
            redirectTo: this.options.redirectTo,
            _csrf_token: this.csrf_token,
        };
        data.lineItems[productId] = productData;

        this.$emitter.publish('addRecommendationToCart', {
            productId: productId,
            elementId: this._nostoElementId
        });

        this._openOffCanvasCarts(this.options.action, JSON.stringify(data));
    }

    /**
     *
     * @param {string} requestUrl
     * @param {{}|FormData} formData
     * @private
     */
    _openOffCanvasCarts(requestUrl, formData) {
        const offCanvasCartInstances = PluginManager.getPluginInstances('OffCanvasCart');
        Iterator.iterate(offCanvasCartInstances, instance => this._openOffCanvasCart(instance, requestUrl, formData));
    }

    /**
     *
     * @param {OffCanvasCartPlugin} instance
     * @param {string} requestUrl
     * @param {{}|FormData} formData
     * @private
     */
    _openOffCanvasCart(instance, requestUrl, formData) {
        instance.openOffCanvas(requestUrl, formData, () => {
            this.$emitter.publish('openOffCanvasCart');
        });
    }
}
