import './init/svg-icons.init';
import './init/nosto-api-keys-validator-service-init';
import './module/nosto';
import './extension/sw-cms/component/sw-cms-sidebar';
import './module/sw-cms/blocks/nosto-integration/nosto-integration-block';
import './module/sw-cms/elements/nosto-integration';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

// Services
import NostoIntegrationService from './core/service/api/nosto-integration.service';

const { Application } = Shopware;

Application.addServiceProvider('NostoIntegrationProviderService', () => {
    return new NostoIntegrationService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService'),
    );
});

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);
