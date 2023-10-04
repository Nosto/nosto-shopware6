const ApiService = Shopware.Classes.ApiService;

class NostoIntegrationService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'nosto-integration') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'NostoIntegrationService';
    }

    scheduleFullProductSync() {
        const apiRoute = `_action/nosto-integration/schedule-full-product-sync`,
            headers = this.getBasicHeaders();

        return this.httpClient.post(apiRoute, {}, {headers});
    }

    clearCaches() {
        const apiRoute = `_action/nosto-integration/clear-cache`,
            headers = this.getBasicHeaders();
        return this.httpClient.post(apiRoute, {}, {headers});
    }
}

export default NostoIntegrationService;
