import ApiService from '../../../../../../../../../../../vendor/shopware/administration/Resources/app/administration/src/core/service/api.service';

class OdNostoCategoriesService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'od-nosto-categories') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'OdNostoCategoriesService';
    }

    sendCategories() {
        const apiRoute = `_action/od-nosto-categories-controller/sync`;
        return this.httpClient.post(
            apiRoute,
            {
                params: {},
                headers: this.getBasicHeaders()
            }
        );
    }
}

export default OdNostoCategoriesService;
