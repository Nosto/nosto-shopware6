const { ApiService } = Shopware.Classes;

class NostoApiKeyValidatorService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'nosto') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'nostoApiKeyValidatorService';
    }

    validate(params) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post('/_action/nosto-integration-api-key-validate', params, { headers });
    }
}

/** @private */
export default NostoApiKeyValidatorService;
