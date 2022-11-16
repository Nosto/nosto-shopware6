import NostoApiKeyValidatorService from "../services/api/nosto-api-keys-validator-service";

Shopware.Service().register('nostoApiKeyValidatorService', (container) => {
    const initContainer = Shopware.Application.getContainer('init');
    return new NostoApiKeyValidatorService(initContainer.httpClient, container.loginService);
});
