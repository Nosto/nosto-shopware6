import NostoApiKeyValidatorService from '../services/api/nosto-api-keys-validator-service';

/** @private */
Shopware.Service().register('nostoApiKeyValidatorService', (container) => {
    const initContainer = Shopware.Application.getContainer('init');
    return new NostoApiKeyValidatorService(initContainer.httpClient, container.loginService);
});
