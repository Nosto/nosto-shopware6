import NostoPlugin from './plugin/nosto.plugin'
import NostoConfiguration from "./plugin/nosto-configuration.plugin";
import './reacting-cookie/reacting-cookie'

// Register plugins via the existing PluginManager
const PluginManager = window.PluginManager;
PluginManager.register('NostoPlugin', NostoPlugin, '[data-nosto-cart-plugin]');
PluginManager.register('NostoConfiguration', NostoConfiguration, '[data-nosto-configuration]');
