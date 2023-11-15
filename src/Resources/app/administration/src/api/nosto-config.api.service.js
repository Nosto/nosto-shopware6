const ApiService = Shopware.Classes.ApiService;

class NostoConfigApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'nosto-config') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'NostoConfigApiService';
    }

    async getValues(salesChannelId, languageId, additionalParams = {}, additionalHeaders = {}) {
        return this.httpClient
            .get('_action/nosto-config', {
                params: { salesChannelId, languageId, ...additionalParams },
                headers: this.getBasicHeaders(additionalHeaders),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    saveValues(values, salesChannelId, languageId, additionalParams = {}, additionalHeaders = {}) {
        return this.httpClient
            .post(
                '_action/nosto-config',
                values,
                {
                    params: { salesChannelId, languageId, ...additionalParams },
                    headers: this.getBasicHeaders(additionalHeaders),
                },
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    batchSave(values, additionalParams = {}, additionalHeaders = {}) {
        return this.httpClient
            .post(
                '_action/nosto-config/batch',
                values,
                {
                    params: { ...additionalParams },
                    headers: this.getBasicHeaders(additionalHeaders),
                },
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

/**
 * @private
 */
export default NostoConfigApiService;
