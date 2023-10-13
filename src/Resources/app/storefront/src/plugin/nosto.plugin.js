import Plugin from 'src/plugin-system/plugin.class';
import PluginManager from 'src/plugin-system/plugin.manager';
import Iterator from 'src/helper/iterator.helper';

export default class NostoPlugin extends Plugin {

    static options = {
        redirectTo: 'frontend.cart.offcanvas',
        action: '/checkout/line-item/add',
    };

    init() {
        window.Nosto = {
            addProductToCart: (id, element, quantity) => {
                this._addMultipleToCart([{'productId': id, 'skuId': id, 'quantity': quantity}], element);
            },
            addMultipleProductsToCart: (ids, element) => {
                this._addMultipleToCart(ids, element);
            },
            addSkuToCart: (idObject, element, quantity) => {
                const quantityObject = {'quantity': quantity};
                this._addMultipleToCart([{...idObject, ...quantityObject}], element);
            },
        };
        this._nostoElementId = (this.el.nextElementSibling.id ? this.el.nextElementSibling.id : '');
    }

    _resolveContextSlotId(element) {
        return element &&
        element.closest('.nosto_element') &&
        element.closest('.nosto_element').getAttribute('id') ?
            element.closest('.nosto_element').getAttribute('id') : this._nostoElementId;
    }

    _addMultipleToCart(ids, element) {
        const data = {
            lineItems: {},
            redirectTo: this.options.redirectTo,
        };

        ids.forEach((item) => {
            data.lineItems[item.skuId] = {
                id: item.skuId,
                quantity: Number.isInteger(item.quantity) ? item.quantity : 1,
                type: 'product',
                referencedId: item.skuId,
                stackable: 1,
                removable: 1,
            };
            this.$emitter.publish('addRecommendationToCart', {
                productId: item.productId,
                elementId: this._resolveContextSlotId(element),
            });
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
