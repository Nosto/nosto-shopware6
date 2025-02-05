document.addEventListener('DOMContentLoaded', () => {
    const productElements = document.querySelectorAll('[role="listitem"]');

    productElements.forEach(product => {
        product.addEventListener('click', async (event) => {
            const dataSource = product.getAttribute('data-source');
            const productNumber = product.getAttribute('product-number');
            const productId = product.getAttribute('product-id');
            const searchQuery = new URLSearchParams(window.location.search).get('search');
            const category = window.location.pathname;
            const categoryId = product.getAttribute('category-id');

            if (!dataSource || !productNumber || !productId) {
                console.error('Missing required attributes: dataSource, productNumber, or productId');
                return;
            }

            const trackingType = searchQuery ? 'search' : (category ? 'category' : 'unknown');

            const body = {
                dataSource,
                productNumber,
                productId,
                trackingType,
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
