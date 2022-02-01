import ApiService from '../../../../../../../../../../../vendor/shopware/administration/Resources/app/administration/src/core/service/api.service';

class OdNostoService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'od-nosto') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'OdNostoService';
    }

    index(ids, body) {
        const apiRoute = `_action/od-nosto/index`;
        return this.httpClient.post(
            apiRoute,
            body,
            {
                params: {},
                headers: this.getBasicHeaders()
            }
        );
    }
}

export default OdNostoService;
