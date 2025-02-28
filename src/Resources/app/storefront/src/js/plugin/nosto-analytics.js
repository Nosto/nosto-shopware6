export default class NostoAnalytics extends window.PluginBaseClass {
    init() {
        this.attachClickEvent();
    }

    async trackProductClick(product) {
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

        if (!apiRoute) {
            console.error('API Route is undefined.');
            return;
        }

        try {
            const response = await fetch(apiRoute, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(body),
            });

            if (!response.ok) {
                console.error('Tracking failed, no response received.');
            }
        } catch (error) {
            console.error('Error sending tracking request:', error);
        }
    }

    attachClickEvent() {
        document.addEventListener('click', (event) => {
            const product = event.target.closest('[nosto-analytics="true"]');
            if (product) {
                this.trackProductClick(product);
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
