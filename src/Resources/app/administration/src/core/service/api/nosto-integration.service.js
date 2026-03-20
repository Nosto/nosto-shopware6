/**
 * @sw-package discovery
 */

const ApiService = Shopware.Classes.ApiService;

/** @private */
class NostoIntegrationService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'nosto-integration') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'NostoIntegrationService';
    }

    scheduleFullProductSync() {
        const apiRoute = '_action/nosto-integration/schedule-full-product-sync';
        const headers = this.getBasicHeaders();

        return this.httpClient.post(apiRoute, {}, { headers });
    }

    deleteRunningFullProductSyncJob() {
        const apiRoute = '_action/nosto-integration/delete-running-full-product-sync-job';
        const headers = this.getBasicHeaders();

        return this.httpClient.post(apiRoute, {}, { headers });
    }

    clearCaches() {
        const apiRoute = '_action/nosto-integration/clear-cache';
        const headers = this.getBasicHeaders();

        return this.httpClient.post(apiRoute, {}, { headers });
    }
}

/** @private */
export default NostoIntegrationService;
