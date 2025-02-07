document.addEventListener('DOMContentLoaded', () => {
    const productElements = document.querySelectorAll('[role="listitem"]');

    function getCookie(name) {
        const cookies = document.cookie.split('; ');
        for (const cookie of cookies) {
            const [key, value] = cookie.split('=');
            if (key === name) {
                return value;
            }
        }
        return null;
    }

    productElements.forEach(product => {
        product.addEventListener('click', async () => {
            const sessionId = getCookie('2c.cId');
            const dataSource = product.getAttribute('data-source');
            const productNumber = product.getAttribute('product-number');
            const productId = product.getAttribute('product-id');
            const searchQuery = document.querySelector('.nosto_search_term')?.textContent?.trim() || new URLSearchParams(window.location.search).get('search');
            const category = document.querySelector('.nosto_category')?.textContent?.trim() || window.location.pathname;
            const categoryId = product.getAttribute('category-id');
            const resultId = document.querySelector('.nosto_result_id')?.textContent?.trim() || null;

            if (!dataSource || !productNumber || !productId || !sessionId) {
                console.error('Missing required attributes: dataSource, productNumber, 2c.cId or productId');
                return;
            }

            const trackingType = searchQuery ? 'search' : (category ? 'category' : 'unknown');

            const body = {
                dataSource,
                productNumber,
                productId,
                trackingType,
                sessionId,
                resultId
            };

            if (trackingType === 'search') {
                body.query = searchQuery;
            } else {
                body.category = category;
                body.categoryId = categoryId;
            }

            try {
                const response = await fetch('/nosto/analytics-tracking', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(body),
                });

                if (!response.ok) {
                    console.error('Tracking failed with status:', response.status);
                }
            } catch (error) {
                console.error('Error sending tracking request:', error);
            }
        });
    });
});
