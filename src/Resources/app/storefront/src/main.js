import NostoPlugin from './js/plugin/nosto.plugin'
import NostoConfiguration from './js/plugin/nosto-configuration.plugin';
import NostoSearchSessionParams from './js/plugin/nosto-search-session-params';
import NostoFilterRange from './js/plugin/listing/filter-range.plugin';
import NostoFilterPropertySelectPlugin from './js/plugin/listing/filter-property-select.plugin';

// Register plugins via the existing PluginManager
const PluginManager = window.PluginManager;
PluginManager.register('NostoPlugin', NostoPlugin, '[data-nosto-cart-plugin]');
PluginManager.register('NostoConfiguration', NostoConfiguration, '[data-nosto-configuration]');
PluginManager.register('NostoSearchSessionParams', NostoSearchSessionParams, '[data-nosto-search-session-params]');
PluginManager.override('FilterRange', NostoFilterRange, '[data-filter-range]');
PluginManager.override('FilterPropertySelect', NostoFilterPropertySelectPlugin, '[data-filter-property-select]');

if (module.hot) {
    module.hot.accept();
}
