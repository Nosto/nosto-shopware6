const ApiService = Shopware.Classes.ApiService;

class OdNostoService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'od-nosto') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'OdNostoService';
    }

    scheduleFullProductSync() {
        const apiRoute = `_action/od-nosto/schedule-full-product-sync`,
            headers = this.getBasicHeaders();

        return this.httpClient.post(apiRoute, {}, {headers});
    }
}

export default OdNostoService;
