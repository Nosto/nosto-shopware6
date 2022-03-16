import Plugin from 'src/plugin-system/plugin.class';
import PluginManager from 'src/plugin-system/plugin.manager';
import Iterator from 'src/helper/iterator.helper';

export default class NostoPlugin extends Plugin {

    static options = {
        redirectSelector: '[name="redirectTo"]',
        redirectParamSelector: '[data-redirect-parameters="true"]',
        redirectTo: 'frontend.cart.offcanvas',
        action: '/checkout/line-item/add'
    };

    init() {
        var self = this;
        window.Nosto = {};
        
        Nosto.addProductToCart = function(id, element) {
           self._onAddToCart(id);
        }
    }

    _onAddToCart(id) {
        this.csrf_token = document.querySelector('.nosto-csrf-token input').value;

        this.data = {};
        this.data['redirectParameters'] = {
            productId: id
        };

        this.data.lineItems = {};
        this.data.lineItems[id] = {};
        this.data.lineItems[id].id = id;
        this.data.lineItems[id].type = 'product';
        this.data.lineItems[id].referencedId = id;
        this.data.lineItems[id].stackable = 1;
        this.data.lineItems[id].removable = 1;
        this.data["redirectTo"] = this.options.redirectTo;
        this.data['_csrf_token'] = this.csrf_token;

        this._openOffCanvasCarts(this.options.action, JSON.stringify(this.data));
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
