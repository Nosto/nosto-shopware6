import NostoPlugin from './plugin/nosto.plugin'

// Register plugins via the existing PluginManager
const PluginManager = window.PluginManager;
PluginManager.register('NostoPlugin', NostoPlugin, '[data-nosto-cart-plugin]');