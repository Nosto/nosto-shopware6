import './init/svg-icons.init';
import './init/nosto-api-keys-validator-service-init';
import './init/nosto-config-api';
import './mixins/nosto-integration-config-component';
import './module/nosto';
import './extension/sw-cms/component/sw-cms-sidebar';
import
'./extension/sw-product/component/sw-product-variants/sw-product-variants-delivery/sw-product-variants-delivery-listing';
import './module/sw-cms/blocks/nosto-integration/nosto-integration-block';
import './module/sw-cms/elements/nosto-integration';
import configurationState from './module/nosto/store/configuration';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

// Services
import NostoIntegrationService from './core/service/api/nosto-integration.service';
import NostoCategoriesService from './core/service/api/nosto-categories.service';

const { Application, State } = Shopware;

State.registerModule('nostoIntegrationConfig', configurationState);

Application.addServiceProvider('NostoIntegrationProviderService', () => {
    return new NostoIntegrationService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService'),
    );
});

Application.addServiceProvider('NostoCategoriesProviderService', () => {
    return new NostoCategoriesService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService'),
    );
});

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);
