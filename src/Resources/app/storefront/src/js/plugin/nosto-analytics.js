document.addEventListener('DOMContentLoaded', () => {
    const productElements = document.querySelectorAll('[role="listitem"]');

    productElements.forEach(product => {
        event.preventDefault();
        product.addEventListener('click', async () => {
            const dataSource = product.getAttribute('data-source');
            const productNumber = product.getAttribute('product-number');
            const productId = product.getAttribute('product-id');

            // TODO: Handle Exception.
            const response = await fetch('/nosto/analytics-tracking', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    dataSource,
                    productNumber,
                    productId
                }),
            });

            console.log(response, "Success");
            alert('Success');
        });

        alert('Success');
    });
});