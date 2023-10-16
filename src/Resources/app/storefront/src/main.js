import NostoPlugin from './plugin/nosto.plugin'
import NostoConfiguration from './plugin/nosto-configuration.plugin';
import NostoSearchSessionParams from './plugin/nosto-search-session-params';
import NostoListingPlugin from './plugin/nosto-listing.plugin';
import './reacting-cookie/reacting-cookie'

// Register plugins via the existing PluginManager
const PluginManager = window.PluginManager;
PluginManager.register('NostoPlugin', NostoPlugin, '[data-nosto-cart-plugin]');
PluginManager.register('NostoConfiguration', NostoConfiguration, '[data-nosto-configuration]');
PluginManager.register('NostoSearchSessionParams', NostoSearchSessionParams, '[data-nosto-search-session-params]');
PluginManager.override('Listing', NostoListingPlugin, '[data-listing]');
