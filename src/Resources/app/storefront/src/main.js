import NostoPlugin from './js/plugin/nosto.plugin'
import NostoConfiguration from './js/plugin/nosto-configuration.plugin';
import NostoSearchSessionParams from './js/plugin/nosto-search-session-params';
import NostoFilterRange from './js/plugin/listing/filter-range.plugin';
import NostoFilterPropertySelectPlugin from './js/plugin/listing/filter-property-select.plugin';
import NostoAnalytics from './js/plugin/nosto-analytics';
import NostoPreviewPlugin from './js/plugin/nosto-preview.plugin';
import NostoAbTests from './js/plugin/nosto-abtests';

// Register plugins via the existing PluginManager
const PluginManager = window.PluginManager;
PluginManager.register('NostoPlugin', NostoPlugin, '[data-nosto-cart-plugin]');
PluginManager.register('NostoConfiguration', NostoConfiguration, '[data-nosto-configuration]');
PluginManager.register('NostoSearchSessionParams', NostoSearchSessionParams, '[data-nosto-search-session-params]');
PluginManager.register('NostoAnalytics', NostoAnalytics, '[data-nosto-analytics]');
PluginManager.register('NostoAbTests', NostoAbTests, '[data-nosto-abtests]');
PluginManager.override('FilterRange', NostoFilterRange, '[data-filter-range]');
PluginManager.override('FilterPropertySelect', NostoFilterPropertySelectPlugin, '[data-filter-property-select]');
PluginManager.register('NostoPreviewPlugin', NostoPreviewPlugin, '[data-nosto-preview]');

if (module.hot) {
    module.hot.accept();
}
