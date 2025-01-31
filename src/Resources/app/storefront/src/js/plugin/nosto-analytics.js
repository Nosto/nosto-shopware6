document.addEventListener('DOMContentLoaded', () => {
    const productElements = document.querySelectorAll('[role="listitem"]');

    productElements.forEach(product => {
        product.addEventListener('click', async (event) => {
            event.preventDefault(); // Ensure the default action is prevented on click

            const dataSource = product.getAttribute('data-source');
            const productNumber = product.getAttribute('product-number');
            const productId = product.getAttribute('product-id');
            const searchQuery = new URLSearchParams(window.location.search).get('search');
            const category = window.location.pathname.split('/').pop();

            if (!dataSource || !productNumber || !productId) {
                console.error('Missing required attributes: dataSource, productNumber, or productId');
                return;
            }

            const trackingType = searchQuery ? 'search' : (category ? 'category' : 'unknown');

            try {
                const response = await fetch('/nosto/analytics-tracking', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        dataSource,
                        productNumber,
                        productId,
                        trackingType,
                        searchQuery,
                        category,
                    }),
                });

                if (!response.ok) {
                    console.error('Tracking failed with status:', response.status);
                }
                console.log(response);
            } catch (error) {
                console.error('Error sending tracking request:', error);
            }
        });
    });
});
