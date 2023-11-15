import NostoConfigApiService from '../api/nosto-config.api.service';

const { Application } = Shopware;

Application.addServiceProvider('NostoConfigApiService', (container) => {
    const initContainer = Application.getContainer('init');

    return new NostoConfigApiService(initContainer.httpClient, container.loginService);
});
