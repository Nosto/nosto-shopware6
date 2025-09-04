window.PluginManager.register('NostoPlugin', () => import('./js/plugin/nosto.plugin'), '[data-nosto-cart-plugin]');
window.PluginManager.register('NostoConfiguration', () => import('./js/plugin/nosto-configuration.plugin'), '[data-nosto-configuration]');
window.PluginManager.register('NostoSearchSessionParams', () => import('./js/plugin/nosto-search-session-params'), '[data-nosto-search-session-params]');
window.PluginManager.register('NostoAnalytics', () => import('./js/plugin/nosto-analytics'), '[data-nosto-analytics]');
window.PluginManager.register('NostoAbTests', () => import('./js/plugin/nosto-abtests'), '[data-nosto-abtests]');
window.PluginManager.override('FilterRange', () => import('./js/plugin/listing/filter-range.plugin'), '[data-filter-range]');
window.PluginManager.override('FilterPropertySelect', () => import('./js/plugin/listing/filter-property-select.plugin'), '[data-filter-property-select]');
window.PluginManager.register('NostoPreviewPlugin', () => import('./js/plugin/nosto-preview.plugin'), '[data-nosto-preview]');

if (module.hot) {
    module.hot.accept();
}
