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
            throw new Error('Missing required attributes: dataSource, productNumber, 2c.cId, or productId');
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
            throw new Error('API Route is undefined.');
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
                throw new Error('Tracking failed, no response received.');
            }
        } catch (error) {
            throw new Error(`Error sending tracking request: ${error.message}`);
        }
    }

    attachClickEvent() {
        document.addEventListener('click', async (event) => {
            const product = event.target.closest('[nosto-analytics="true"]');

            if (!product) return;
            if (product.hasAttribute('data-tracking-in-progress')) return;

            product.setAttribute('data-tracking-in-progress', 'true');
            try {
                await this.trackProductClick(product);
            } catch (error) {
                console.error('Error tracking product:', error);
            }
            product.removeAttribute('data-tracking-in-progress');
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
