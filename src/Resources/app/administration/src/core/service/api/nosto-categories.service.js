const ApiService = Shopware.Classes.ApiService;

/** @private */
class NostoCategoriesService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'nosto-categories') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'NostoCategoriesService';
    }

    sendCategories() {
        const apiRoute = '_action/nosto-categories-controller/sync';
        return this.httpClient.post(
            apiRoute,
            {
                params: {},
                headers: this.getBasicHeaders(),
            },
        );
    }
}

/** @private */
export default NostoCategoriesService;
