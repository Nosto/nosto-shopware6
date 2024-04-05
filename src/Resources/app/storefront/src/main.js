window.PluginManager.register('NostoPlugin', () => import('./js/plugin/nosto.plugin'), '[data-nosto-cart-plugin]');
window.PluginManager.register('NostoConfiguration', () => import('./js/plugin/nosto-configuration.plugin'), '[data-nosto-configuration]');
window.PluginManager.register('NostoSearchSessionParams', () => import('./js/plugin/nosto-search-session-params'), '[data-nosto-search-session-params]');
window.PluginManager.override('FilterRange', () => import('./js/plugin/listing/filter-range.plugin'), '[data-filter-range]');
window.PluginManager.override('FilterPropertySelect', () => import('./js/plugin/listing/filter-property-select.plugin'), '[data-filter-property-select]');

if (module.hot) {
    module.hot.accept();
}
