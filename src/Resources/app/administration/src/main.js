import './init/svg-icons.init';
import './init/nosto-api-keys-validator-service-init';
import './module/nosto';
import './extension/sw-cms/component/sw-cms-sidebar';
import './module/sw-cms/blocks/od-nosto/od-nosto-block';
import './module/sw-cms/elements/od-nosto';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

// Services
import OdNostoService from './core/service/api/od-nosto.service';

const {Application} = Shopware;

Application.addServiceProvider('OdNostoProviderService', () => {
    return new OdNostoService(Shopware.Application.getContainer('init').httpClient, Shopware.Service('loginService'),);
})

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);
