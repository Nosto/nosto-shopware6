import HttpClient from 'src/service/http-client.service';

export default class NostoAnalytics extends window.PluginBaseClass {
    init() {
        this._client = new HttpClient();
        this.attachClickEvent();
    }

    async trackProductClick(event) {
        const product = event.currentTarget;
        if (!product) return;

        const sessionId = this.getCookie('2c.cId');
        const productData = {
            dataSource: product.getAttribute('data-source'),
            productNumber: product.getAttribute('product-number'),
            productId: product.getAttribute('product-id'),
            categoryId: product.getAttribute('category-id'),
        };
        const searchQuery = document.querySelector('.nosto_search_term')?.textContent?.trim()
            || new URLSearchParams(window.location.search).get('search');
        const category = document.querySelector('.nosto_category')?.textContent?.trim() || window.location.pathname;
        const resultId = document.querySelector('.nosto_result_id')?.textContent?.trim() || null;

        const trackingType = searchQuery ? 'search' : category ? 'category' : 'unknown';

        if (!sessionId || !productData.dataSource || !productData.productNumber || !productData.productId) {
            console.error('Missing required attributes: dataSource, productNumber, 2c.cId or productId');
            return;
        }

        const body = {
            ...productData,
            sessionId,
            trackingType,
            resultId,
            ...(trackingType === 'search' ? { query: searchQuery } : { category }),
        };

        const apiRoute = window.router['storefront.nosto.analytics-tracking'];

        try {
            const response = await this._client.post(apiRoute, JSON.stringify(body), {
                headers: { 'Content-Type': 'application/json' }
            });

            if (!response) {
                console.error('Tracking failed, no response received.');
            }
        } catch (error) {
            console.error('Error sending tracking request:', error);
        }
    }

    attachClickEvent() {
        document.querySelectorAll('[role="listitem"]').forEach(product => {
            if (!product.hasEventListenerAttached) {
                product.addEventListener('click', this.trackProductClick.bind(this));
                product.hasEventListenerAttached = true;
            }
        });
    }

    getCookie(name) {
        const cookies = document.cookie.split('; ');
        for (const cookie of cookies) {
            const [key, value] = cookie.split('=');
            if (key === name) {
                return value;
            }
        }
        return null;
    }
}
