window.PluginManager.register('NostoPlugin', () => import('./js/plugin/nosto.plugin'), '[data-nosto-cart-plugin]');
window.PluginManager.register('NostoConfiguration', () => import('./js/plugin/nosto-configuration.plugin'), '[data-nosto-configuration]');
window.PluginManager.register('NostoSearchSessionParams', () => import('./js/plugin/nosto-search-session-params'), '[data-nosto-search-session-params]');
window.PluginManager.override('FilterRange', () => import('./js/plugin/listing/filter-range.plugin'), '[data-filter-range]');
window.PluginManager.override('FilterPropertySelect', () => import('./js/plugin/listing/filter-property-select.plugin'), '[data-filter-property-select]');

if (module.hot) {
    module.hot.accept();
}
document.addEventListener('DOMContentLoaded', () => {
    const productElements = document.querySelectorAll('[role="listitem"]');

    productElements.forEach(product => {
        event.preventDefault();
        product.addEventListener('click', async () => {
            const dataSource = product.getAttribute('data-source');
            const productNumber = product.getAttribute('product-number');

            console.log('dataSource:', dataSource);
            console.log('productNumber:', productNumber);
            alert('data');
            if (!dataSource || !productNumber) {
                console.error('Missing data-source or product-number attribute.');
                alert('data-missing');
                return;
            }

            try {
                const response = await fetch('/nosto/track-product-click', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        dataSource,
                        productNumber,
                    }),
                });

                console.log(response, "Success");
                alert('Success');
                if (response.ok) {
                    console.log('Product click tracked successfully.');
                } else {
                    console.error('Failed to track product click:', response.statusText);
                    alert('error1');
                }
            } catch (error) {
                console.error('Error tracking product click:', error);
                alert('error2');
            }
        });
    });
});

